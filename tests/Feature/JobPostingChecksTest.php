<?php

use App\Enums\ApplicationOutcomeStatus;
use App\Enums\JobPostingGap;
use App\Enums\SkillImportance;
use App\Models\Application;
use App\Models\JobPosting;
use App\Models\JobPostingDailyStat;
use App\Models\Skill;
use App\Services\JobPerformance;
use App\Support\JobPostingChecks;

function wellWrittenJob(array $attributes = []): JobPosting
{
    return JobPosting::factory()->create([
        'salary_min' => 50000,
        'salary_max' => 70000,
        'description' => '<p>'.str_repeat('word ', 300).'</p>',
        'expires_at' => now()->addDays(20),
        ...$attributes,
    ]);
}

test('a complete posting has nothing to suggest', function () {
    expect(JobPostingChecks::for(wellWrittenJob()))->toBe([]);
});

test('a posting with no salary at all is flagged, one with either end is not', function () {
    expect(JobPostingChecks::for(wellWrittenJob(['salary_min' => null, 'salary_max' => null])))->toBe([JobPostingGap::NoSalary])
        ->and(JobPostingChecks::for(wellWrittenJob(['salary_min' => 50000, 'salary_max' => null])))->toBe([])
        ->and(JobPostingChecks::for(wellWrittenJob(['salary_min' => null, 'salary_max' => 70000])))->toBe([]);
});

test('more than ten required skills is flagged; nice-to-haves do not count', function () {
    $job = wellWrittenJob();
    $skills = collect(range(1, 12))->map(fn ($i) => Skill::create(['name' => "Skill {$i}", 'slug' => "skill-{$i}"]));

    $job->skills()->attach($skills->take(10)->mapWithKeys(fn ($skill) => [$skill->id => ['importance' => SkillImportance::Required]]));
    $job->skills()->attach($skills->slice(10)->mapWithKeys(fn ($skill) => [$skill->id => ['importance' => SkillImportance::NiceToHave]]));

    expect(JobPostingChecks::for($job->fresh()))->toBe([]);

    $job->skills()->updateExistingPivot($skills->last()->id, ['importance' => SkillImportance::Required]);

    expect(JobPostingChecks::for($job->fresh()))->toBe([JobPostingGap::TooManyRequiredSkills]);
});

test('descriptions are measured in words, in any script', function (string $description, array $expected) {
    expect(JobPostingChecks::for(wellWrittenJob(['description' => $description])))->toBe($expected);
})->with([
    'short' => ['<p>'.str_repeat('word ', 99).'</p>', [JobPostingGap::ShortDescription]],
    'just enough' => ['<p>'.str_repeat('word ', 100).'</p>', []],
    'long' => ['<p>'.str_repeat('word ', 1001).'</p>', [JobPostingGap::LongDescription]],
    'Bengali' => ['<p>'.str_repeat('আমরা একজন ডেভেলপার খুঁজছি ', 30).'</p>', []],
    'tags are not words' => ['<p><strong>'.str_repeat('a</strong> <em>', 60).'</em></p>', [JobPostingGap::ShortDescription]],
]);

test('a live posting closing within three days is flagged', function () {
    expect(JobPostingChecks::for(wellWrittenJob(['expires_at' => now()->addDays(2)])))->toBe([JobPostingGap::ExpiringSoon])
        ->and(JobPostingChecks::for(wellWrittenJob(['expires_at' => now()->addDays(4)])))->toBe([])
        ->and(JobPostingChecks::for(wellWrittenJob(['expires_at' => now()->subDay()])))->toBe([]);

    $draft = JobPosting::factory()->draft()->create(['expires_at' => now()->addDay()]);

    expect(JobPostingChecks::for($draft))->not->toContain(JobPostingGap::ExpiringSoon);
});

test('many readers and few applicants is flagged, once there are a hundred views', function (int $views, int $applications, bool $flagged) {
    $job = wellWrittenJob();
    JobPostingDailyStat::create(['job_posting_id' => $job->id, 'date' => today()->toDateString(), 'views' => $views]);
    Application::factory()->count($applications)->for($job)->create();

    $report = app(JobPerformance::class)->for($job->company, $job);

    expect(in_array(JobPostingGap::LowApplyRate, JobPostingChecks::for($job, $report), true))->toBe($flagged);
})->with([
    'low rate' => [200, 3, true],
    'fair rate' => [200, 4, false],
    'too few views to say' => [99, 0, false],
]);

test('rejecting most applicants unseen is flagged, once there are ten', function (int $unseen, int $total, bool $flagged) {
    $job = wellWrittenJob();
    $applications = Application::factory()->count($total)->for($job)->create();
    $applications->take($unseen)->each(fn (Application $application) => $application->update(['outcome_status' => ApplicationOutcomeStatus::Rejected]));

    $report = app(JobPerformance::class)->for($job->company, $job);

    expect(in_array(JobPostingGap::ManyRejectedUnseen, JobPostingChecks::for($job, $report), true))->toBe($flagged);
})->with([
    'half of ten' => [5, 10, true],
    'under half' => [4, 10, false],
    'too few to say' => [9, 9, false],
]);

test('company-wide numbers are never read as one posting\'s', function () {
    $job = wellWrittenJob();
    Application::factory()->count(10)->for($job)->create(['outcome_status' => ApplicationOutcomeStatus::Rejected]);

    $companyWide = app(JobPerformance::class)->for($job->company);

    expect(JobPostingChecks::for($job, $companyWide))->toBe([]);
});

test('every gap has a message', function (JobPostingGap $gap) {
    expect($gap->message())->toBeString()->not->toBeEmpty();
})->with(JobPostingGap::cases());
