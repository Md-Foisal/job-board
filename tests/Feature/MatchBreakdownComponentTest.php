<?php

use App\Models\CandidatePreference;
use App\Models\ExperienceRecord;
use App\Models\JobPosting;
use App\Models\Skill;
use App\Models\User;
use Livewire\Livewire;

/**
 * A posting with fixed values in everything the breakdown shows.
 */
function componentPosting(array $attributes = []): JobPosting
{
    $posting = JobPosting::factory()->create(array_merge([
        'employment_type' => 'full-time',
        'workplace_type' => 'hybrid',
        'min_experience_years' => 2,
        'salary_min' => 50000,
        'salary_max' => 80000,
        'salary_currency' => 'BDT',
        'salary_period' => 'monthly',
        'salary_negotiable' => false,
    ], $attributes));

    $posting->skills()->attach(Skill::firstOrCreate(['name' => 'Laravel']), ['importance' => 'required']);
    $posting->skills()->attach(Skill::firstOrCreate(['name' => 'Go']), ['importance' => 'required']);
    $posting->skills()->attach(Skill::firstOrCreate(['name' => 'Vue']), ['importance' => 'nice-to-have']);

    return $posting;
}

function filledCandidate(): User
{
    $candidate = candidateUser();
    $profile = $candidate->candidateProfile;

    $profile->skills()->attach(Skill::firstOrCreate(['name' => 'Laravel']), ['proficiency' => 'advanced']);
    CandidatePreference::factory()->for($profile)->create([
        'desired_salary_min' => 70000,
        'desired_salary_max' => null,
        'desired_salary_currency' => 'bdt',
        'preferred_workplace_type' => 'remote',
        'preferred_employment_type' => null,
    ]);
    ExperienceRecord::factory()->for($profile)->create(['start_date' => '2022-01-01', 'end_date' => '2023-02-01']);

    return $candidate;
}

test('a candidate gets the breakdown on a job page, loaded after the page', function () {
    $posting = componentPosting();

    $this->actingAs(filledCandidate())
        ->get(route('jobs.show', $posting))
        ->assertOk()
        ->assertSee('Loading how you match this job');
});

test('guests, employers and staff get no breakdown on a job page', function (Closure $viewer) {
    $posting = componentPosting();
    $user = $viewer($posting);

    if ($user) {
        $this->actingAs($user);
    }

    $this->get(route('jobs.show', $posting))
        ->assertOk()
        ->assertDontSee('Loading how you match this job');
})->with([
    'guest' => [fn () => null],
    'employer of the company' => [fn (JobPosting $posting) => employerUser($posting->company)],
    'staff' => [fn () => staffUser()],
]);

test('a company member previewing a draft gets no breakdown', function () {
    $posting = componentPosting();
    $posting->forceFill(['availability_status' => 'draft', 'published_at' => null])->save();

    $this->actingAs(employerUser($posting->company))
        ->get(route('jobs.show', $posting))
        ->assertOk()
        ->assertDontSee('Loading how you match this job');
});

test('the breakdown shows the score, each skill and each check with both sides in words', function () {
    $posting = componentPosting();

    $this->travelTo(now()->setDate(2026, 6, 15));

    Livewire::actingAs(filledCandidate())
        ->test('match-breakdown', ['jobPosting' => $posting])
        ->assertSee('How you match')
        ->assertSee('40% match')
        ->assertSee('Only you can see this.')
        ->assertSeeInOrder(['Required skills', 'Laravel', '(you have this)', 'Go', '(missing from your profile)'])
        ->assertSeeInOrder(['Nice to have', 'Vue', '(missing from your profile)'])
        ->assertSeeInOrder(['Salary', 'This job: BDT 50,000–80,000 a month', 'You: From BDT 70,000 a month', 'Fits'])
        ->assertSeeInOrder(['Workplace', 'This job: Hybrid', 'You: Remote', 'Doesn&#039;t fit'], false)
        ->assertSeeInOrder(['Employment type', 'This job: Full-time', 'You: No preference', 'Can&#039;t compare'], false)
        ->assertSeeInOrder(['Experience', 'This job: 2+ years', 'You: 1 year 2 months', 'Doesn&#039;t fit'], false)
        ->assertSee('Employers see only the skills match.');
});

test('pay not given by the month is shown as a rough monthly figure', function () {
    $posting = componentPosting([
        'salary_min' => 600000,
        'salary_max' => 1200000,
        'salary_period' => 'yearly',
    ]);

    Livewire::actingAs(filledCandidate())
        ->test('match-breakdown', ['jobPosting' => $posting])
        ->assertSee('This job: About BDT 50,000–100,000 a month');
});

test('an empty profile is asked to fill in, with a way to do it from a CV', function () {
    $posting = componentPosting();

    Livewire::actingAs(candidateUser())
        ->test('match-breakdown', ['jobPosting' => $posting])
        ->assertSee('Your profile is empty')
        ->assertSee(route('candidate.documents.index'))
        ->assertDontSee('This job:');
});

test('a candidate with no skills but other details is asked for skills to get a score', function () {
    $posting = componentPosting();
    $candidate = candidateUser();
    CandidatePreference::factory()->for($candidate->candidateProfile)->create(['preferred_workplace_type' => 'hybrid']);

    Livewire::actingAs($candidate)
        ->test('match-breakdown', ['jobPosting' => $posting])
        ->assertDontSee('% match')
        ->assertSee('to get a match score.')
        ->assertSee('This job: Hybrid');
});

test('the component shows nothing to anyone but a candidate, or on a job the public cannot see', function () {
    $posting = componentPosting();

    Livewire::test('match-breakdown', ['jobPosting' => $posting])
        ->assertDontSee('How you match');

    Livewire::actingAs(employerUser($posting->company))
        ->test('match-breakdown', ['jobPosting' => $posting])
        ->assertDontSee('How you match');

    $posting->forceFill(['moderation_status' => 'pending'])->save();

    Livewire::actingAs(filledCandidate())
        ->test('match-breakdown', ['jobPosting' => $posting])
        ->assertDontSee('How you match');
});

test('the preference form asks for salary by the month', function () {
    $this->actingAs(candidateUser())
        ->get(route('candidate.preferences.edit'))
        ->assertSee('Desired monthly salary, min')
        ->assertSee('Desired monthly salary, max')
        ->assertDontSee('employers match you');
});
