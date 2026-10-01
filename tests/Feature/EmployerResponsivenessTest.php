<?php

use App\Enums\ApplicationOutcomeStatus;
use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\Company;
use App\Models\JobPosting;
use App\Services\EmployerResponsiveness;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Exceptions;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
    $this->company = Company::factory()->create();
    $this->job = JobPosting::factory()->for($this->company)->create();
});

function appliedDaysAgo(JobPosting $job, int $days): Application
{
    return Application::factory()->for($job)->create(['created_at' => now()->subDays($days)]);
}

function answeredAfter(Application $application, int $days): void
{
    ApplicationEvent::forceCreate([
        'application_id' => $application->id,
        'from_stage' => 'new',
        'to_stage' => 'shortlisted',
        'created_at' => $application->created_at->addDays($days),
    ]);
}

function withdrawnAfter(Application $application, int $days): void
{
    $application->update(['outcome_status' => ApplicationOutcomeStatus::Withdrawn]);

    ApplicationEvent::forceCreate([
        'application_id' => $application->id,
        'from_outcome_status' => ApplicationOutcomeStatus::Active->value,
        'to_outcome_status' => ApplicationOutcomeStatus::Withdrawn->value,
        'created_at' => $application->created_at->addDays($days),
    ]);
}

/**
 * $answered of $total applications, three weeks old, answered on day 3.
 */
function applicationsAnswered(JobPosting $job, int $answered, int $total): void
{
    foreach (range(1, $total) as $index) {
        $application = appliedDaysAgo($job, 21);

        if ($index <= $answered) {
            answeredAfter($application, 3);
        }
    }
}

function responsiveness(): EmployerResponsiveness
{
    return app(EmployerResponsiveness::class);
}

test('three in four applications answered within fourteen days earns the mark', function () {
    applicationsAnswered($this->job, 9, 12);

    expect(responsiveness()->measure($this->company))->toBe(['counted' => 12, 'answered' => 9])
        ->and(responsiveness()->percentFor($this->company))->toBe(75);
});

test('one short of three in four earns nothing, and the shortfall is never shown', function () {
    applicationsAnswered($this->job, 8, 12);

    expect(responsiveness()->percentFor($this->company))->toBeNull();
});

test('fewer than ten applications earn nothing, however well they were answered', function () {
    applicationsAnswered($this->job, 9, 9);

    expect(responsiveness()->percentFor($this->company))->toBeNull();
});

test('the share is rounded down, never up', function () {
    expect(EmployerResponsiveness::percentIfResponsive(29, 30))->toBe(96)
        ->and(EmployerResponsiveness::percentIfResponsive(10, 10))->toBe(100)
        ->and(EmployerResponsiveness::percentIfResponsive(15, 20))->toBe(75)
        ->and(EmployerResponsiveness::percentIfResponsive(14, 19))->toBeNull();
});

test('an answer on the fourteenth day is in time, one on the fifteenth is not', function () {
    answeredAfter(appliedDaysAgo($this->job, 30), 14);
    answeredAfter(appliedDaysAgo($this->job, 30), 15);

    expect(responsiveness()->measure($this->company))->toBe(['counted' => 2, 'answered' => 1]);
});

test('only applications that have had their full fourteen days count, from the ninety days before that', function () {
    appliedDaysAgo($this->job, 13);
    appliedDaysAgo($this->job, 15);
    appliedDaysAgo($this->job, 103);
    appliedDaysAgo($this->job, 105);

    expect(responsiveness()->measure($this->company))->toBe(['counted' => 2, 'answered' => 0]);
});

test('a withdrawal is never an answer, and one before the company\'s time was up leaves the application out', function () {
    withdrawnAfter(appliedDaysAgo($this->job, 30), 3);

    withdrawnAfter(appliedDaysAgo($this->job, 30), 20);

    $answeredFirst = appliedDaysAgo($this->job, 30);
    answeredAfter($answeredFirst, 2);
    withdrawnAfter($answeredFirst, 5);

    expect(responsiveness()->measure($this->company))->toBe(['counted' => 2, 'answered' => 1]);
});

test('a decision that was undone is no answer', function () {
    $application = appliedDaysAgo($this->job, 30);

    foreach ([ApplicationOutcomeStatus::Rejected, ApplicationOutcomeStatus::Active] as $to) {
        ApplicationEvent::forceCreate([
            'application_id' => $application->id,
            'to_outcome_status' => $to->value,
            'created_at' => $application->created_at->addDay(),
        ]);
    }

    expect(responsiveness()->measure($this->company))->toBe(['counted' => 1, 'answered' => 0]);
});

test('another company\'s applications do not count', function () {
    applicationsAnswered(JobPosting::factory()->create(), 10, 10);

    expect(responsiveness()->measure($this->company))->toBe(['counted' => 0, 'answered' => 0]);
});

test('the figure is kept for an hour, since answering an application moves no cache', function () {
    applicationsAnswered($this->job, 10, 10);
    expect(responsiveness()->percentFor($this->company))->toBe(100);

    applicationsAnswered($this->job, 0, 10);

    $this->travel(59)->minutes();
    expect(responsiveness()->percentFor($this->company))->toBe(100);

    $this->travel(2)->minutes();
    expect(responsiveness()->percentFor($this->company))->toBeNull();
});

test('the mark is on the company page with its share, and on the company\'s job pages', function () {
    applicationsAnswered($this->job, 10, 10);

    $this->get(route('companies.show', $this->company))
        ->assertOk()
        ->assertSee('Responsive employer')
        ->assertSee('Answered 100% of recent applications within 14 days.');

    $this->get(route('jobs.show', $this->job))
        ->assertOk()
        ->assertSee('Responsive employer');
});

test('without the mark there is nothing in its place', function () {
    applicationsAnswered($this->job, 0, 10);

    $this->get(route('companies.show', $this->company))
        ->assertOk()
        ->assertDontSee('Responsive employer');
});

test('a failure costs the mark and is reported, never the page', function () {
    Exceptions::fake();
    applicationsAnswered($this->job, 10, 10);

    $this->partialMock(EmployerResponsiveness::class)
        ->shouldReceive('measure')
        ->andThrow(new RuntimeException('Database went away'));

    $this->get(route('companies.show', $this->company))
        ->assertOk()
        ->assertDontSee('Responsive employer');

    $this->get(route('jobs.show', $this->job))->assertOk();

    Exceptions::assertReported(RuntimeException::class);
});
