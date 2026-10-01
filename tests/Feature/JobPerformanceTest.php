<?php

use App\Enums\ApplicationOutcomeStatus;
use App\Enums\ApplicationStage;
use App\Enums\SkillImportance;
use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\JobPostingDailyStat;
use App\Models\Skill;
use App\Models\User;
use App\Services\JobPerformance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
    $this->company = Company::factory()->create();
    $this->job = JobPosting::factory()->for($this->company)->create([
        'published_at' => now()->subDays(40),
        'expires_at' => now()->addDays(20),
    ]);
});

function performance(Company $company, ?JobPosting $job = null, int $days = 30)
{
    return app(JobPerformance::class)->for($company, $job, $days);
}

function appliedAt(JobPosting $job, string $at, array $attributes = []): Application
{
    return Application::factory()->for($job)->create([...$attributes, 'created_at' => $at]);
}

function stageMove(Application $application, ?string $to, string $at, ?User $by = null): ApplicationEvent
{
    return ApplicationEvent::forceCreate([
        'application_id' => $application->id,
        'changed_by_id' => $by?->id,
        'to_stage' => $to,
        'created_at' => $at,
    ]);
}

function outcomeChange(Application $application, ApplicationOutcomeStatus $to, string $at, ?User $by = null): ApplicationEvent
{
    $application->update(['outcome_status' => $to]);

    return ApplicationEvent::forceCreate([
        'application_id' => $application->id,
        'changed_by_id' => $by?->id,
        'from_outcome_status' => ApplicationOutcomeStatus::Active->value,
        'to_outcome_status' => $to->value,
        'created_at' => $at,
    ]);
}

function viewsFor(JobPosting $job, string $date, int $views): void
{
    JobPostingDailyStat::create(['job_posting_id' => $job->id, 'date' => $date, 'views' => $views]);
}

test('views are summed over the range, day by day, and only for the company\'s own postings', function () {
    viewsFor($this->job, '2026-06-15', 4);
    viewsFor($this->job, '2026-06-10', 6);
    viewsFor($this->job, '2026-05-16', 100);
    viewsFor(JobPosting::factory()->create(), '2026-06-15', 50);

    $report = performance($this->company, days: 7);

    expect($report->views)->toBe(10)
        ->and($report->dailyViews)->toHaveCount(7)
        ->and(array_key_first($report->dailyViews))->toBe('2026-06-09')
        ->and($report->dailyViews['2026-06-10'])->toBe(6)
        ->and($report->dailyViews['2026-06-11'])->toBe(0)
        ->and($report->dailyViews['2026-06-15'])->toBe(4);
});

test('the report says when view counting started, so a range reaching further back is not read as nobody coming', function () {
    viewsFor($this->job, '2026-06-12', 3);

    $report = performance($this->company, days: 30);

    expect($report->viewsCountedSince->toDateString())->toBe('2026-06-12')
        ->and($report->viewsCoverRange())->toBeFalse()
        ->and(performance($this->company, days: 7)->viewsCountedSince->toDateString())->toBe('2026-06-12');

    expect(performance(Company::factory()->create())->viewsCountedSince)->toBeNull();
});

test('views counted before the range started cover it', function () {
    viewsFor($this->job, '2026-05-01', 1);

    expect(performance($this->company, days: 7)->viewsCoverRange())->toBeTrue();
});

test('applications are counted on the day they arrived, inside the range only', function () {
    appliedAt($this->job, '2026-06-15 09:00:00');
    appliedAt($this->job, '2026-06-14 18:00:00');
    appliedAt($this->job, '2026-06-14 08:00:00');
    appliedAt($this->job, '2026-06-08 23:59:00');

    $report = performance($this->company, days: 7);

    expect($report->applications)->toBe(3)
        ->and($report->dailyApplications['2026-06-14'])->toBe(2)
        ->and($report->dailyApplications['2026-06-15'])->toBe(1)
        ->and(array_sum($report->dailyApplications))->toBe(3);
});

test('the apply rate waits for twenty views', function () {
    viewsFor($this->job, '2026-06-15', 19);
    appliedAt($this->job, '2026-06-15 09:00:00');

    expect(performance($this->company)->applyRate)->toBeNull();

    DB::table('job_posting_daily_stats')->update(['views' => 20]);
    cache()->flush();

    expect(performance($this->company)->applyRate)->toBe(5.0);
});

test('the funnel follows the range\'s applications to the furthest step each reached, whenever that was', function () {
    $shortlisted = appliedAt($this->job, '2026-06-01 09:00:00', ['stage' => ApplicationStage::Shortlisted]);
    stageMove($shortlisted, 'shortlisted', '2026-06-02 09:00:00');

    $straightToInterview = appliedAt($this->job, '2026-06-01 09:00:00', ['stage' => ApplicationStage::Interview]);
    stageMove($straightToInterview, 'interview', '2026-06-03 09:00:00');

    // Moved back down a step later: it still reached interview once.
    $movedBack = appliedAt($this->job, '2026-06-01 09:00:00', ['stage' => ApplicationStage::Shortlisted]);
    stageMove($movedBack, 'interview', '2026-06-03 09:00:00');
    stageMove($movedBack, 'shortlisted', '2026-06-04 09:00:00');

    $hired = appliedAt($this->job, '2026-06-01 09:00:00', ['stage' => ApplicationStage::Offer]);
    stageMove($hired, 'shortlisted', '2026-06-02 09:00:00');
    stageMove($hired, 'offer', '2026-06-05 09:00:00');
    outcomeChange($hired, ApplicationOutcomeStatus::Hired, '2026-06-06 09:00:00');

    appliedAt($this->job, '2026-06-01 09:00:00');

    $beforeRange = appliedAt($this->job, '2026-05-01 09:00:00', ['stage' => ApplicationStage::Offer]);
    stageMove($beforeRange, 'offer', '2026-06-10 09:00:00');

    expect(performance($this->company)->funnel)->toBe([
        'shortlisted' => 4,
        'interview' => 3,
        'offer' => 1,
        'hired' => 1,
    ]);
});

test('an application rejected without ever leaving New counts as rejected unseen', function () {
    $unseen = appliedAt($this->job, '2026-06-01 09:00:00');
    outcomeChange($unseen, ApplicationOutcomeStatus::Rejected, '2026-06-02 09:00:00');

    $seen = appliedAt($this->job, '2026-06-01 09:00:00', ['stage' => ApplicationStage::Shortlisted]);
    stageMove($seen, 'shortlisted', '2026-06-02 09:00:00');
    outcomeChange($seen, ApplicationOutcomeStatus::Rejected, '2026-06-03 09:00:00');

    $movedBackToNew = appliedAt($this->job, '2026-06-01 09:00:00');
    stageMove($movedBackToNew, 'shortlisted', '2026-06-02 09:00:00');
    stageMove($movedBackToNew, 'new', '2026-06-02 10:00:00');
    outcomeChange($movedBackToNew, ApplicationOutcomeStatus::Rejected, '2026-06-03 09:00:00');

    $withdrawn = appliedAt($this->job, '2026-06-01 09:00:00');
    outcomeChange($withdrawn, ApplicationOutcomeStatus::Withdrawn, '2026-06-02 09:00:00');

    expect(performance($this->company)->rejectedUnseen)->toBe(1);
});

test('the first response is the company\'s first change, never the candidate withdrawing', function () {
    $employer = employerUser($this->company);

    foreach ([5, 10, 20] as $hours) {
        $application = appliedAt($this->job, '2026-06-01 08:00:00');
        stageMove($application, 'shortlisted', CarbonImmutable::parse('2026-06-01 08:00:00')->addHours($hours)->toDateTimeString(), $employer);
        stageMove($application, 'interview', '2026-06-12 08:00:00', $employer);
    }

    $rejected = appliedAt($this->job, '2026-06-01 08:00:00');
    outcomeChange($rejected, ApplicationOutcomeStatus::Rejected, '2026-06-03 08:00:00', $employer);

    $withdrawn = appliedAt($this->job, '2026-06-01 08:00:00');
    outcomeChange($withdrawn, ApplicationOutcomeStatus::Withdrawn, '2026-06-01 09:00:00', $withdrawn->candidateProfile->user);

    $report = performance($this->company);

    expect($report->responded)->toBe(4)
        ->and($report->firstResponseMedianHours)->toBe(15.0);

    $firstResponses = JobPerformance::firstResponses(Application::query()->where('job_posting_id', $this->job->id));

    expect($firstResponses->keys()->all())->not->toContain($withdrawn->id)
        ->and($firstResponses[$rejected->id]->toDateTimeString())->toBe('2026-06-03 08:00:00');
});

test('a median needs at least three values', function () {
    foreach ([5, 10] as $hours) {
        $application = appliedAt($this->job, '2026-06-01 08:00:00');
        stageMove($application, 'shortlisted', CarbonImmutable::parse('2026-06-01 08:00:00')->addHours($hours)->toDateTimeString());
    }

    $report = performance($this->company);

    expect($report->responded)->toBe(2)
        ->and($report->firstResponseMedianHours)->toBeNull();
});

test('waiting counts every active application nobody has touched, with the oldest one\'s date', function () {
    appliedAt($this->job, '2026-03-01 08:00:00');
    appliedAt($this->job, '2026-06-14 08:00:00');

    $touched = appliedAt($this->job, '2026-06-10 08:00:00');
    stageMove($touched, 'new', '2026-06-11 08:00:00');

    $withdrawn = appliedAt($this->job, '2026-06-10 08:00:00');
    outcomeChange($withdrawn, ApplicationOutcomeStatus::Withdrawn, '2026-06-11 08:00:00');

    $report = performance($this->company, days: 7);

    expect($report->waiting)->toBe(2)
        ->and($report->oldestWaitingSince->toDateTimeString())->toBe('2026-03-01 08:00:00');
});

test('time to hire is the median for hires made in the range, from the day each one applied', function () {
    foreach (['2026-06-01', '2026-05-20', '2026-04-01'] as $applied) {
        $application = appliedAt($this->job, $applied.' 08:00:00');
        outcomeChange($application, ApplicationOutcomeStatus::Hired, '2026-06-11 08:00:00');
    }

    $beforeRange = appliedAt($this->job, '2026-04-01 08:00:00');
    outcomeChange($beforeRange, ApplicationOutcomeStatus::Hired, '2026-05-01 08:00:00');

    $report = performance($this->company);

    expect($report->hires)->toBe(3)
        ->and($report->timeToHireMedianDays)->toBe(22.0);
});

test('time to fill runs from publishing to each posting\'s first hire', function () {
    $first = appliedAt($this->job, '2026-05-10 08:00:00');
    outcomeChange($first, ApplicationOutcomeStatus::Hired, '2026-05-26 08:00:00');
    $second = appliedAt($this->job, '2026-05-10 08:00:00');
    outcomeChange($second, ApplicationOutcomeStatus::Hired, '2026-06-10 08:00:00');

    $unfilled = JobPosting::factory()->for($this->company)->create(['published_at' => now()->subDays(5)]);
    appliedAt($unfilled, '2026-06-12 08:00:00');

    expect(performance($this->company)->timeToFillDays)->toBe([$this->job->id => 20])
        ->and(performance($this->company, $unfilled)->timeToFillDays)->toBe([]);
});

test('applicants are grouped by the same skill-match score the employer sees', function () {
    $laravel = Skill::create(['name' => 'Laravel', 'slug' => 'laravel']);
    $vue = Skill::create(['name' => 'Vue', 'slug' => 'vue']);
    $this->job->skills()->attach([
        $laravel->id => ['importance' => SkillImportance::Required],
        $vue->id => ['importance' => SkillImportance::NiceToHave],
    ]);

    $both = appliedAt($this->job, '2026-06-01 08:00:00');
    $both->candidateProfile->skills()->attach([$laravel->id => ['proficiency' => 'advanced'], $vue->id => ['proficiency' => 'advanced']]);

    $laravelOnly = appliedAt($this->job, '2026-06-01 08:00:00');
    $laravelOnly->candidateProfile->skills()->attach([$laravel->id => ['proficiency' => 'advanced']]);

    $vueOnly = appliedAt($this->job, '2026-06-01 08:00:00');
    $vueOnly->candidateProfile->skills()->attach([$vue->id => ['proficiency' => 'advanced']]);

    // Lists no skills at all: no score, so no bucket.
    appliedAt($this->job, '2026-06-01 08:00:00');

    expect(performance($this->company)->skillMatch)->toBe(['low' => 1, 'medium' => 1, 'high' => 1]);
});

test('without skills on the posting there is no skill-match spread', function () {
    $application = appliedAt($this->job, '2026-06-01 08:00:00');
    $application->candidateProfile->skills()->attach([Skill::create(['name' => 'Go', 'slug' => 'go'])->id => ['proficiency' => 'advanced']]);

    expect(performance($this->company)->skillMatch)->toBeNull();
});

test('saves are a running total', function () {
    DB::table('saved_jobs')->insert([
        ['user_id' => User::factory()->create()->id, 'job_posting_id' => $this->job->id],
        ['user_id' => User::factory()->create()->id, 'job_posting_id' => $this->job->id],
    ]);

    expect(performance($this->company)->saves)->toBe(2)
        ->and(performance($this->company, $this->job)->saves)->toBe(2);
});

test('one posting\'s report covers that posting alone and says where it stands', function () {
    $other = JobPosting::factory()->for($this->company)->draft()->create(['published_at' => null]);
    appliedAt($this->job, '2026-06-10 08:00:00');
    appliedAt($other, '2026-06-10 08:00:00');

    $report = performance($this->company, $this->job);

    expect($report->applications)->toBe(1)
        ->and($report->job)->toMatchArray(['status' => 'live', 'live_days' => 40, 'expires_in_days' => 20])
        ->and(performance($this->company, $other)->job)->toMatchArray(['status' => 'draft', 'live_days' => null, 'expires_in_days' => null])
        ->and(performance($this->company)->job)->toBeNull();
});

test('the report is cached for ten minutes and reads back whole', function () {
    viewsFor($this->job, '2026-06-15', 25);
    appliedAt($this->job, '2026-06-15 09:00:00');
    $before = performance($this->company);

    appliedAt($this->job, '2026-06-15 10:00:00');
    $cached = performance($this->company);

    expect($cached)->toEqual($before)
        ->and($cached->applications)->toBe(1);

    $this->travel(11)->minutes();

    expect(performance($this->company)->applications)->toBe(2);
});

test('another company\'s posting or an unknown range is refused', function () {
    expect(fn () => performance($this->company, JobPosting::factory()->create()))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => performance($this->company, days: 14))
        ->toThrow(InvalidArgumentException::class);
});

test('an application\'s current stage counts in the funnel even with no history behind it', function () {
    appliedAt($this->job, '2026-06-01 09:00:00', ['stage' => ApplicationStage::Interview]);

    expect(performance($this->company)->funnel)->toMatchArray(['shortlisted' => 1, 'interview' => 1, 'offer' => 0]);
});
