<?php

use App\Enums\SkillImportance;
use App\Models\Category;
use App\Models\JobPosting;
use App\Models\Skill;
use App\Services\MatchScoreCalculator;
use Livewire\Livewire;

/**
 * @param  array<int, array{0: Skill, 1: SkillImportance}>  $skills
 */
function postingNeeding(string $title, array $skills): JobPosting
{
    $posting = JobPosting::factory()->create(['title' => $title]);

    foreach ($skills as [$skill, $importance]) {
        $posting->skills()->attach($skill->id, ['importance' => $importance->value]);
    }

    return $posting;
}

/**
 * Rafi knows Laravel and Vue. Five postings, created oldest first in the
 * order they should rank, so "newest first" would put them the other way
 * round.
 */
beforeEach(function () {
    $this->laravel = Skill::create(['name' => 'Laravel']);
    $this->vue = Skill::create(['name' => 'Vue']);
    $go = Skill::create(['name' => 'Go']);

    $required = SkillImportance::Required;
    $nice = SkillImportance::NiceToHave;

    $this->ranked = [
        postingNeeding('Laravel and Vue Role', [[$this->laravel, $required], [$this->vue, $required]]),
        postingNeeding('Laravel and Go Role', [[$this->laravel, $required], [$go, $required]]),
        postingNeeding('Go with Some Laravel Role', [[$go, $required], [$this->laravel, $nice]]),
        postingNeeding('Go Only Role', [[$go, $required]]),
        postingNeeding('No Skills Listed Role', []),
    ];

    $this->rafi = candidateUser();
    $this->rafi->candidateProfile->skills()->attach([
        $this->laravel->id => ['proficiency' => 'advanced'],
        $this->vue->id => ['proficiency' => 'intermediate'],
    ]);
});

test('best match ranks postings by the same score their cards show, unscored ones last', function () {
    $mine = [$this->laravel->id, $this->vue->id];

    $order = JobPosting::query()->sortBy('match', null, $mine)->pluck('id')->all();

    $scores = collect($this->ranked)->map(fn (JobPosting $posting) => app(MatchScoreCalculator::class)
        ->calculate($posting->load('skills'), collect($mine)));

    expect($order)->toBe(collect($this->ranked)->pluck('id')->all())
        ->and($scores->all())->toBe([100, 50, 33, 0, null]);
});

test('a candidate with skills can sort the search by best match', function () {
    Livewire::actingAs($this->rafi)
        ->test('pages::job-search')
        ->assertSee('Best match')
        ->set('sort', 'match')
        ->assertSeeInOrder(collect($this->ranked)->pluck('title')->all());
});

test('the category page offers best match too', function () {
    $category = Category::create(['name' => 'Engineering', 'slug' => 'engineering']);
    $category->jobPostings()->attach(collect($this->ranked)->pluck('id'));

    Livewire::actingAs($this->rafi)
        ->test('pages::category-show', ['categoryModel' => $category])
        ->set('sort', 'match')
        ->assertSeeInOrder(collect($this->ranked)->pluck('title')->all());
});

test('best match is not offered to anyone with nothing to match on', function (?Closure $who) {
    $component = $who === null ? Livewire::test('pages::job-search') : Livewire::actingAs($who())->test('pages::job-search');

    $component->assertDontSee('Best match');
})->with([
    'a guest' => [null],
    'a candidate with no skills' => [fn () => candidateUser()],
    'an employer' => [fn () => employerUser()],
]);

test('an address asking for best match opens newest first when there is nothing to match', function () {
    Livewire::withQueryParams(['sort' => 'match'])
        ->test('pages::job-search')
        ->assertSet('sort', 'newest');
});
