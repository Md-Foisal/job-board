<?php

use App\Actions\MergeCategory;
use App\Actions\MergeSkill;
use App\Enums\StaffRole;
use App\Filament\Resources\Skills\Pages\ManageSkills;
use App\Models\CandidateProfile;
use App\Models\Category;
use App\Models\JobPosting;
use App\Models\Skill;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function importanceOf(JobPosting $posting, Skill $skill): ?string
{
    return DB::table('job_posting_skill')
        ->where('job_posting_id', $posting->id)
        ->where('skill_id', $skill->id)
        ->value('importance');
}

it('keeps the skill and category lists away from moderators', function () {
    $this->actingAs(staffWithTwoFactor(StaffRole::Moderator))
        ->get('/admin/skills')
        ->assertForbidden();
});

it('refuses a skill that only differs from an existing one by case', function () {
    Skill::create(['name' => 'React']);
    $this->actingAs(staffWithTwoFactor(StaffRole::SuperAdmin));

    Livewire::test(ManageSkills::class)
        ->callAction(TestAction::make('create'), data: ['name' => 'react'])
        ->assertHasActionErrors(['name']);

    expect(Skill::count())->toBe(1);
});

it('gives names that slug the same way their own addresses', function () {
    $c = Skill::create(['name' => 'C']);
    $sharp = Skill::create(['name' => 'C#']);

    expect($c->slug)->toBe('c')
        ->and($sharp->slug)->toBe('c-2');
});

it('only lets a skill nobody uses be removed outright', function () {
    $unused = Skill::create(['name' => 'COBOL']);
    $used = Skill::create(['name' => 'Laravel']);
    JobPosting::factory()->create()->skills()->attach($used->id, ['importance' => 'required']);
    $this->actingAs(staffWithTwoFactor(StaffRole::SuperAdmin));

    Livewire::test(ManageSkills::class)
        ->assertActionVisible(TestAction::make('delete')->table($unused))
        ->assertActionHidden(TestAction::make('delete')->table($used));
});

it('merges a duplicate skill without anyone losing a match', function () {
    $react = Skill::create(['name' => 'React']);
    $reactJs = Skill::create(['name' => 'ReactJS']);

    $onlyDuplicate = JobPosting::factory()->create();
    $onlyDuplicate->skills()->attach($reactJs->id, ['importance' => 'required']);

    $both = JobPosting::factory()->create();
    $both->skills()->attach($reactJs->id, ['importance' => 'required']);
    $both->skills()->attach($react->id, ['importance' => 'nice-to-have']);

    $candidate = CandidateProfile::factory()->create();
    $candidate->skills()->attach($reactJs->id, ['proficiency' => 'advanced']);
    $candidate->skills()->attach($react->id, ['proficiency' => 'beginner']);

    app(MergeSkill::class)($reactJs, $react);

    expect(importanceOf($onlyDuplicate, $react))->toBe('required')
        ->and(importanceOf($both, $react))->toBe('required')
        ->and(DB::table('job_posting_skill')->where('skill_id', $reactJs->id)->count())->toBe(0)
        ->and(DB::table('candidate_profile_skill')->where('candidate_profile_id', $candidate->id)->where('skill_id', $react->id)->value('proficiency'))->toBe('advanced')
        ->and($reactJs->fresh()->trashed())->toBeTrue();
});

it('refiles a merged category\'s postings and forwards its old address for good', function () {
    $engineering = Category::create(['name' => 'Engineering']);
    $software = Category::create(['name' => 'Software']);
    $posting = JobPosting::factory()->create();
    $posting->categories()->attach($software->id);

    app(MergeCategory::class)($software, $engineering);

    expect($posting->categories()->pluck('categories.id')->all())->toBe([$engineering->id]);

    $this->get(route('categories.show', ['categoryModel' => 'software']))
        ->assertStatus(301)
        ->assertRedirect(route('categories.show', $engineering));
});

it('keeps an old address one hop away even after a second merge', function () {
    $a = Category::create(['name' => 'Alpha']);
    $b = Category::create(['name' => 'Beta']);
    $c = Category::create(['name' => 'Gamma']);

    app(MergeCategory::class)($a, $b);
    app(MergeCategory::class)($b, $c);

    $this->get('/categories/alpha')->assertRedirect(route('categories.show', $c));
});
