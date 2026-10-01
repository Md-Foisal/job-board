<?php

use App\Enums\MatchCheck;
use App\Enums\MatchCheckResult;
use App\Models\CandidatePreference;
use App\Models\CandidateProfile;
use App\Models\ExperienceRecord;
use App\Models\JobPosting;
use App\Models\Skill;
use App\Services\MatchScoreCalculator;
use App\Support\ExperienceDuration;
use App\Support\MatchBreakdown;
use Illuminate\Support\Carbon;

/**
 * A posting with nothing random in the fields a breakdown compares;
 * each test sets only the ones it is about.
 *
 * @param  array<string, string>  $skills  skill name => importance
 */
function breakdownPosting(array $attributes = [], array $skills = []): JobPosting
{
    $posting = JobPosting::factory()->create(array_merge([
        'employment_type' => 'full-time',
        'workplace_type' => 'remote',
        'min_experience_years' => null,
        'salary_min' => null,
        'salary_max' => null,
        'salary_currency' => null,
        'salary_period' => null,
        'salary_negotiable' => false,
    ], $attributes));

    foreach ($skills as $name => $importance) {
        $posting->skills()->attach(Skill::firstOrCreate(['name' => $name]), ['importance' => $importance]);
    }

    return $posting;
}

/**
 * @param  array<int, string>  $skills
 * @param  array<string, mixed>|null  $preference
 * @param  array<int, array{0: string, 1: ?string}>  $experience  [start, end] dates
 */
function breakdownCandidate(array $skills = [], ?array $preference = null, array $experience = []): CandidateProfile
{
    $profile = CandidateProfile::factory()->create();

    foreach ($skills as $name) {
        $profile->skills()->attach(Skill::firstOrCreate(['name' => $name]), ['proficiency' => 'advanced']);
    }

    if ($preference !== null) {
        CandidatePreference::factory()->for($profile)->create(array_merge([
            'desired_salary_min' => null,
            'desired_salary_max' => null,
            'desired_salary_currency' => null,
            'preferred_workplace_type' => null,
            'preferred_employment_type' => null,
        ], $preference));
    }

    foreach ($experience as [$start, $end]) {
        ExperienceRecord::factory()->for($profile)->create(['start_date' => $start, 'end_date' => $end]);
    }

    return $profile;
}

function breakdownOf(JobPosting $posting, CandidateProfile $profile): MatchBreakdown
{
    return app(MatchScoreCalculator::class)->breakdown($posting, $profile);
}

test('skills are split into matched and missing, required and nice to have, around the same score', function () {
    $posting = breakdownPosting(skills: [
        'Laravel' => 'required',
        'Go' => 'required',
        'Vue' => 'nice-to-have',
        'Docker' => 'nice-to-have',
    ]);
    $profile = breakdownCandidate(['Laravel', 'Vue', 'Figma']);

    $breakdown = breakdownOf($posting, $profile);

    expect(skillNames($breakdown->matchedRequired))->toBe(['Laravel'])
        ->and(skillNames($breakdown->missingRequired))->toBe(['Go'])
        ->and(skillNames($breakdown->matchedNiceToHave))->toBe(['Vue'])
        ->and(skillNames($breakdown->missingNiceToHave))->toBe(['Docker'])
        // Laravel (2) + Vue (1) of 2 + 2 + 1 + 1.
        ->and($breakdown->score)->toBe(50)
        ->and($breakdown->score)->toBe(app(MatchScoreCalculator::class)->calculate(
            $posting->fresh(),
            $profile->skills()->pluck('skills.id'),
        ));
});

test('a candidate with no skills gets no score, and every job skill is missing', function () {
    $posting = breakdownPosting(skills: ['Laravel' => 'required', 'Vue' => 'nice-to-have']);

    $breakdown = breakdownOf($posting, breakdownCandidate());

    expect($breakdown->score)->toBeNull()
        ->and(skillNames($breakdown->missingRequired))->toBe(['Laravel'])
        ->and(skillNames($breakdown->missingNiceToHave))->toBe(['Vue'])
        ->and($breakdown->matchedRequired)->toBeEmpty();
});

test('salary is compared monthly, in the same currency, against the candidate\'s floor', function (array $job, array $candidate, MatchCheckResult $expected) {
    $posting = breakdownPosting($job);
    $profile = breakdownCandidate(preference: $candidate);

    expect(breakdownOf($posting, $profile)->check(MatchCheck::Salary))->toBe($expected);
})->with([
    'the range reaches the floor' => [
        ['salary_min' => 50000, 'salary_max' => 80000, 'salary_currency' => 'BDT', 'salary_period' => 'monthly'],
        ['desired_salary_min' => 80000, 'desired_salary_currency' => 'BDT'],
        MatchCheckResult::Fits,
    ],
    'the range stops below the floor' => [
        ['salary_min' => 50000, 'salary_max' => 80000, 'salary_currency' => 'BDT', 'salary_period' => 'monthly'],
        ['desired_salary_min' => 90000, 'desired_salary_currency' => 'BDT'],
        MatchCheckResult::Misses,
    ],
    'a yearly salary counts as monthly' => [
        ['salary_min' => 600000, 'salary_max' => 1200000, 'salary_currency' => 'BDT', 'salary_period' => 'yearly'],
        ['desired_salary_min' => 100000, 'desired_salary_currency' => 'BDT'],
        MatchCheckResult::Fits,
    ],
    'currency letters in any case' => [
        ['salary_min' => 3000, 'salary_max' => 4000, 'salary_currency' => 'USD', 'salary_period' => 'monthly'],
        ['desired_salary_min' => 3500, 'desired_salary_currency' => 'usd'],
        MatchCheckResult::Fits,
    ],
    'different currencies are not converted' => [
        ['salary_min' => 3000, 'salary_max' => 4000, 'salary_currency' => 'USD', 'salary_period' => 'monthly'],
        ['desired_salary_min' => 80000, 'desired_salary_currency' => 'BDT'],
        MatchCheckResult::Unknown,
    ],
    'only a minimum, already above the floor' => [
        ['salary_min' => 90000, 'salary_currency' => 'BDT', 'salary_period' => 'monthly'],
        ['desired_salary_min' => 80000, 'desired_salary_currency' => 'BDT'],
        MatchCheckResult::Fits,
    ],
    'only a minimum, below the floor, might still stretch' => [
        ['salary_min' => 50000, 'salary_currency' => 'BDT', 'salary_period' => 'monthly'],
        ['desired_salary_min' => 80000, 'desired_salary_currency' => 'BDT'],
        MatchCheckResult::Unknown,
    ],
    'a negotiable salary' => [
        ['salary_negotiable' => true],
        ['desired_salary_min' => 80000, 'desired_salary_currency' => 'BDT'],
        MatchCheckResult::Unknown,
    ],
    'negotiable, even with figures left on it' => [
        ['salary_negotiable' => true, 'salary_min' => 50000, 'salary_max' => 80000, 'salary_currency' => 'BDT', 'salary_period' => 'monthly'],
        ['desired_salary_min' => 60000, 'desired_salary_currency' => 'BDT'],
        MatchCheckResult::Unknown,
    ],
    'the candidate gave no floor' => [
        ['salary_min' => 50000, 'salary_max' => 80000, 'salary_currency' => 'BDT', 'salary_period' => 'monthly'],
        ['desired_salary_max' => 90000, 'desired_salary_currency' => 'BDT'],
        MatchCheckResult::Unknown,
    ],
    'the candidate gave no currency' => [
        ['salary_min' => 50000, 'salary_max' => 80000, 'salary_currency' => 'BDT', 'salary_period' => 'monthly'],
        ['desired_salary_min' => 60000],
        MatchCheckResult::Unknown,
    ],
]);

test('with no preferences saved, salary, workplace and employment type are unknown', function () {
    $posting = breakdownPosting(['salary_min' => 50000, 'salary_max' => 80000, 'salary_currency' => 'BDT', 'salary_period' => 'monthly']);

    $breakdown = breakdownOf($posting, breakdownCandidate());

    expect($breakdown->check(MatchCheck::Salary))->toBe(MatchCheckResult::Unknown)
        ->and($breakdown->check(MatchCheck::Workplace))->toBe(MatchCheckResult::Unknown)
        ->and($breakdown->check(MatchCheck::Employment))->toBe(MatchCheckResult::Unknown);
});

test('workplace and employment type must match exactly', function (?string $workplace, ?string $employment, MatchCheckResult $workplaceResult, MatchCheckResult $employmentResult) {
    $posting = breakdownPosting(['workplace_type' => 'hybrid', 'employment_type' => 'contract']);
    $profile = breakdownCandidate(preference: [
        'preferred_workplace_type' => $workplace,
        'preferred_employment_type' => $employment,
    ]);

    $breakdown = breakdownOf($posting, $profile);

    expect($breakdown->check(MatchCheck::Workplace))->toBe($workplaceResult)
        ->and($breakdown->check(MatchCheck::Employment))->toBe($employmentResult);
})->with([
    'both the same' => ['hybrid', 'contract', MatchCheckResult::Fits, MatchCheckResult::Fits],
    'remote wanted, hybrid offered' => ['remote', 'full-time', MatchCheckResult::Misses, MatchCheckResult::Misses],
    'no preference' => [null, null, MatchCheckResult::Unknown, MatchCheckResult::Unknown],
]);

test('experience is compared in months against the years a posting asks for', function (?int $years, array $experience, MatchCheckResult $expected) {
    $this->travelTo(Carbon::parse('2026-06-15'));

    $posting = breakdownPosting(['min_experience_years' => $years]);
    $profile = breakdownCandidate(experience: $experience);

    expect(breakdownOf($posting, $profile)->check(MatchCheck::Experience))->toBe($expected);
})->with([
    'two full years' => [2, [['2022-01-01', '2023-12-01']], MatchCheckResult::Fits],
    'a month short' => [2, [['2022-01-01', '2023-11-01']], MatchCheckResult::Misses],
    'overlapping roles count once' => [2, [['2022-01-01', '2022-12-01'], ['2022-06-01', '2023-05-01']], MatchCheckResult::Misses],
    'a role still going counts to this month' => [2, [['2024-07-01', null]], MatchCheckResult::Fits],
    'no experience asked for' => [0, [], MatchCheckResult::Fits],
    'the posting does not say' => [null, [['2020-01-01', null]], MatchCheckResult::Unknown],
    'no work history listed' => [1, [], MatchCheckResult::Unknown],
]);

test('work time is counted in calendar months, both ends included, overlaps once', function () {
    $today = Carbon::parse('2026-06-15');
    $record = fn (string $start, ?string $end) => new ExperienceRecord(['start_date' => $start, 'end_date' => $end]);

    expect(ExperienceDuration::months(collect(), $today))->toBe(0)
        ->and(ExperienceDuration::months(collect([$record('2021-01-01', '2021-12-01')]), $today))->toBe(12)
        ->and(ExperienceDuration::months(collect([$record('2021-03-10', '2021-03-25')]), $today))->toBe(1)
        // Jan–Dec 2021 and Jun 2021–Mar 2022 are one stretch, Jan 2021–Mar 2022.
        ->and(ExperienceDuration::months(collect([
            $record('2021-06-01', '2022-03-01'),
            $record('2021-01-01', '2021-12-01'),
        ]), $today))->toBe(15)
        // A role inside another adds nothing.
        ->and(ExperienceDuration::months(collect([
            $record('2020-01-01', '2020-12-01'),
            $record('2020-04-01', '2020-06-01'),
        ]), $today))->toBe(12)
        // Separate roles with a gap add up; the gap does not.
        ->and(ExperienceDuration::months(collect([
            $record('2019-01-01', '2019-06-01'),
            $record('2020-01-01', '2020-06-01'),
        ]), $today))->toBe(12)
        // Ongoing: Jan through Jun 2026.
        ->and(ExperienceDuration::months(collect([$record('2026-01-01', null)]), $today))->toBe(6)
        // A start still to come, or an end past today, counts only up to now.
        ->and(ExperienceDuration::months(collect([$record('2026-09-01', null)]), $today))->toBe(0)
        ->and(ExperienceDuration::months(collect([$record('2026-05-01', '2027-05-01')]), $today))->toBe(2);
});

test('a profile with nothing to compare is empty; any skill, role or preference is enough', function () {
    $posting = breakdownPosting(['min_experience_years' => 0], ['Laravel' => 'required']);

    expect(breakdownOf($posting, breakdownCandidate())->isEmpty())->toBeTrue()
        // A preference row with nothing chosen in it is still nothing.
        ->and(breakdownOf($posting, breakdownCandidate(preference: []))->isEmpty())->toBeTrue()
        ->and(breakdownOf($posting, breakdownCandidate(['Go']))->isEmpty())->toBeFalse()
        ->and(breakdownOf($posting, breakdownCandidate(experience: [['2022-01-01', null]]))->isEmpty())->toBeFalse()
        ->and(breakdownOf($posting, breakdownCandidate(preference: ['preferred_workplace_type' => 'remote']))->isEmpty())->toBeFalse();
});
