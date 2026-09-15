<?php

use App\Enums\AvailabilityStatus;
use App\Enums\MembershipRole;
use App\Enums\ModerationStatus;
use App\Enums\SkillImportance;
use App\Models\Category;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Skill;
use Livewire\Livewire;

function jobFormPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Senior Laravel Developer',
        'description' => 'Build and maintain our hiring platform.',
        'employmentType' => 'full-time',
        'workplaceType' => 'remote',
        'locationCountry' => 'Bangladesh',
        'expiresAt' => now()->addMonth()->toDateString(),
    ], $overrides);
}

function fillJobForm($component, array $overrides = [])
{
    foreach (jobFormPayload($overrides) as $field => $value) {
        $component->set($field, $value);
    }

    return $component;
}

test('a plain member cannot open the posting form', function () {
    $company = Company::factory()->create();

    $this->actingAs(employerUser($company, MembershipRole::Member))
        ->get(route('employer.jobs.create', $company))
        ->assertForbidden();
});

test('a manager can post a job', function () {
    $company = Company::factory()->create();
    $manager = employerUser($company, MembershipRole::Manager);
    $category = Category::create(['name' => 'Backend', 'slug' => 'backend']);
    $skill = Skill::create(['name' => 'Laravel', 'slug' => 'laravel']);

    fillJobForm(Livewire::actingAs($manager)->test('pages::employer.job-form', ['company' => $company]))
        ->set('categories', [$category->id])
        ->set('skills', [$skill->id => SkillImportance::Required->value])
        ->set('screeningQuestions', ['Why this role?', ''])
        ->call('saveAndPublish')
        ->assertHasNoErrors();

    $job = $company->jobPostings()->sole();

    expect($job->title)->toBe('Senior Laravel Developer');
    expect($job->slug)->toBe('senior-laravel-developer');
    expect($job->posted_by_id)->toBe($manager->id);
    expect($job->availability_status)->toBe(AvailabilityStatus::Active);
    expect($job->published_at)->not->toBeNull();
    expect($job->categories)->toHaveCount(1);
    expect($job->skills)->toHaveCount(1);

    // The empty row in the form is not a question.
    expect($job->screeningQuestions)->toHaveCount(1);
    expect($job->screeningQuestions->first()->question_text)->toBe('Why this role?');
});

test('a published posting still has to clear moderation', function () {
    $company = Company::factory()->create();

    fillJobForm(Livewire::actingAs(employerUser($company, MembershipRole::Owner))
        ->test('pages::employer.job-form', ['company' => $company]))
        ->call('saveAndPublish');

    expect($company->jobPostings()->sole()->moderation_status)->toBe(ModerationStatus::Pending);
});

test('saving as a draft does not publish it', function () {
    $company = Company::factory()->create();

    fillJobForm(Livewire::actingAs(employerUser($company, MembershipRole::Owner))
        ->test('pages::employer.job-form', ['company' => $company]))
        ->call('save');

    $job = $company->jobPostings()->sole();

    expect($job->availability_status)->toBe(AvailabilityStatus::Draft);
    expect($job->published_at)->toBeNull();
});

test('a remote role must still say where someone may work from', function () {
    $company = Company::factory()->create();

    fillJobForm(
        Livewire::actingAs(employerUser($company, MembershipRole::Owner))
            ->test('pages::employer.job-form', ['company' => $company]),
        ['locationCountry' => '']
    )->call('saveAndPublish')->assertHasErrors('locationCountry');
});

test('the top of the pay range cannot be below the bottom', function () {
    $company = Company::factory()->create();

    fillJobForm(
        Livewire::actingAs(employerUser($company, MembershipRole::Owner))
            ->test('pages::employer.job-form', ['company' => $company])
    )->set('salaryMin', '90000')
        ->set('salaryMax', '40000')
        ->call('saveAndPublish')
        ->assertHasErrors('salaryMax');
});

test('editing keeps the public address even when the title changes', function () {
    $company = Company::factory()->create();
    $job = JobPosting::factory()->for($company)->create(['title' => 'Old Title', 'slug' => 'old-title']);

    Livewire::actingAs(employerUser($company, MembershipRole::Owner))
        ->test('pages::employer.job-form', ['company' => $company, 'jobPosting' => $job])
        ->set('title', 'Completely Different Title')
        ->call('saveAndPublish')
        ->assertHasNoErrors();

    expect($job->fresh()->slug)->toBe('old-title');
    expect($job->fresh()->title)->toBe('Completely Different Title');
});

test('someone from another company cannot edit a posting', function () {
    $company = Company::factory()->create();
    $job = JobPosting::factory()->for($company)->create();

    $this->actingAs(employerUser())
        ->get(route('employer.jobs.edit', ['company' => $company, 'job_posting' => $job]))
        ->assertForbidden();
});

test('every member can see the listings, only managers get the actions', function () {
    $company = Company::factory()->create();
    JobPosting::factory()->for($company)->create(['title' => 'Backend Engineer']);

    $this->actingAs(employerUser($company, MembershipRole::Member))
        ->get(route('employer.jobs.index', $company))
        ->assertOk()
        ->assertSee('Backend Engineer')
        ->assertDontSee('Post a job');
});

test('closing a posting stops applications', function () {
    $company = Company::factory()->create();
    $job = JobPosting::factory()->for($company)->create(['availability_status' => AvailabilityStatus::Active]);

    Livewire::actingAs(employerUser($company, MembershipRole::Owner))
        ->test('pages::employer.job-listings', ['company' => $company])
        ->call('close', $job->id);

    expect($job->fresh()->availability_status)->toBe(AvailabilityStatus::Closed);
});

test('a plain member cannot close a posting', function () {
    $company = Company::factory()->create();
    $job = JobPosting::factory()->for($company)->create(['availability_status' => AvailabilityStatus::Active]);

    Livewire::actingAs(employerUser($company, MembershipRole::Member))
        ->test('pages::employer.job-listings', ['company' => $company])
        ->call('close', $job->id)
        ->assertForbidden();

    expect($job->fresh()->availability_status)->toBe(AvailabilityStatus::Active);
});

test('reopening something long expired moves its date too', function () {
    $company = Company::factory()->create();
    $job = JobPosting::factory()->for($company)->create([
        'availability_status' => AvailabilityStatus::Expired,
        'expires_at' => now()->subMonths(2),
    ]);

    Livewire::actingAs(employerUser($company, MembershipRole::Owner))
        ->test('pages::employer.job-listings', ['company' => $company])
        ->call('reopen', $job->id);

    $job->refresh();

    expect($job->availability_status)->toBe(AvailabilityStatus::Active);
    expect($job->expires_at->isFuture())->toBeTrue();
});

test('extending a lapsed posting gives it a full month from today', function () {
    $company = Company::factory()->create();
    $job = JobPosting::factory()->for($company)->create(['expires_at' => now()->subMonths(2)]);

    Livewire::actingAs(employerUser($company, MembershipRole::Owner))
        ->test('pages::employer.job-listings', ['company' => $company])
        ->call('extend', $job->id);

    expect($job->fresh()->expires_at->isAfter(now()->addWeeks(3)))->toBeTrue();
});

test('a duplicate starts as an unapproved draft', function () {
    $company = Company::factory()->create();
    $skill = Skill::create(['name' => 'Laravel', 'slug' => 'laravel']);
    $job = JobPosting::factory()->for($company)->create([
        'title' => 'Backend Engineer',
        'availability_status' => AvailabilityStatus::Active,
    ]);
    $job->skills()->attach($skill->id, ['importance' => SkillImportance::Required]);
    $job->screeningQuestions()->create(['question_text' => 'Why us?', 'display_order' => 0]);

    // Approved on the original -- the copy must not inherit it.
    $job->moderation_status = ModerationStatus::Approved;
    $job->save();

    Livewire::actingAs(employerUser($company, MembershipRole::Owner))
        ->test('pages::employer.job-listings', ['company' => $company])
        ->call('duplicate', $job->id);

    $copy = $company->jobPostings()->where('id', '!=', $job->id)->sole();

    expect($copy->title)->toBe('Backend Engineer (copy)');
    expect($copy->slug)->not->toBe($job->slug);
    expect($copy->availability_status)->toBe(AvailabilityStatus::Draft);
    expect($copy->moderation_status)->toBe(ModerationStatus::Pending);
    expect($copy->skills)->toHaveCount(1);
    expect($copy->screeningQuestions)->toHaveCount(1);
});
