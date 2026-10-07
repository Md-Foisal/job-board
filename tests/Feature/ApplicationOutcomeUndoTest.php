<?php

use App\Actions\ChangeApplicationOutcome;
use App\Actions\UndoApplicationOutcome;
use App\Enums\ApplicationOutcomeStatus;
use App\Enums\MembershipRole;
use App\Models\Application;
use App\Models\Company;
use App\Models\JobPosting;
use App\Services\JobPerformance;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    config(['queue.default' => 'database']);
    Event::fake([NotificationSent::class]);

    $this->company = Company::factory()->create();
    $this->job = JobPosting::factory()->for($this->company)->create(['published_at' => now()->subDays(5)]);
    $this->application = Application::factory()->for($this->job)->create([
        'candidate_profile_id' => candidateUser()->candidateProfile->id,
    ]);
    $this->manager = employerUser($this->company, MembershipRole::Manager);
});

function decide($test, ApplicationOutcomeStatus $outcome): void
{
    app(ChangeApplicationOutcome::class)($test->application, $test->manager, $outcome);
}

function runQueue($test): void
{
    $test->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true]);
}

function undoPage($test, $user)
{
    return Livewire::actingAs($user)->test('pages::employer.application-detail', [
        'company' => $test->company,
        'application' => $test->application->fresh(),
    ]);
}

test('the email waits out the undo window, then goes', function () {
    decide($this, ApplicationOutcomeStatus::Rejected);

    expect(DB::table('jobs')->count())->toBe(1);

    $this->travel(Application::UNDO_MINUTES - 1)->minutes();
    runQueue($this);

    Event::assertNotDispatched(NotificationSent::class);

    $this->travel(2)->minutes();
    runQueue($this);

    Event::assertDispatchedTimes(NotificationSent::class, 1);
    expect(DB::table('jobs')->count())->toBe(0);
});

test('undoing within the window reopens the application, records it, and the email never goes', function () {
    decide($this, ApplicationOutcomeStatus::Rejected);
    $this->travel(3)->minutes();

    undoPage($this, $this->manager)
        ->assertSee('Undo')
        ->call('undoDecision')
        ->assertHasNoErrors();

    $application = $this->application->fresh();
    $undo = $application->events()->first();

    expect($application->outcome_status)->toBe(ApplicationOutcomeStatus::Active)
        ->and($application->decided_at)->toBeNull()
        ->and($undo->from_outcome_status)->toBe('rejected')
        ->and($undo->to_outcome_status)->toBe('active')
        ->and($undo->changed_by_id)->toBe($this->manager->id);

    $this->travel(Application::UNDO_MINUTES)->minutes();
    runQueue($this);

    Event::assertNotDispatched(NotificationSent::class);
});

test('after the window the decision is final: no undo, and the email has gone', function () {
    decide($this, ApplicationOutcomeStatus::Hired);
    $this->travel(Application::UNDO_MINUTES + 1)->minutes();
    runQueue($this);

    $page = undoPage($this, $this->manager)->assertDontSee('Undo');

    expect($this->manager->can('undoOutcome', $this->application->fresh()))->toBeFalse();

    $page->call('undoDecision');

    expect($this->application->fresh()->outcome_status)->toBe(ApplicationOutcomeStatus::Hired)
        ->and(app(UndoApplicationOutcome::class)($this->application, $this->manager))->toBeNull();
    Event::assertDispatchedTimes(NotificationSent::class, 1);
});

test('a plain member cannot undo', function () {
    decide($this, ApplicationOutcomeStatus::Rejected);

    undoPage($this, employerUser($this->company))
        ->assertDontSee('Undo')
        ->call('undoDecision')
        ->assertForbidden();

    expect($this->application->fresh()->outcome_status)->toBe(ApplicationOutcomeStatus::Rejected);
});

test('deciding again after an undo sends one email, for the decision that stands', function () {
    decide($this, ApplicationOutcomeStatus::Rejected);
    $this->travel(1)->minutes();
    app(UndoApplicationOutcome::class)($this->application, $this->manager);
    $this->travel(1)->minutes();
    decide($this, ApplicationOutcomeStatus::Hired);

    $this->travel(Application::UNDO_MINUTES + 1)->minutes();
    runQueue($this);
    runQueue($this);

    Event::assertDispatchedTimes(NotificationSent::class, 1);
    Event::assertDispatched(NotificationSent::class, fn ($event) => $event->notification->outcome === ApplicationOutcomeStatus::Hired);
});

test('the team sees the undo in the history; the candidate never sees either', function () {
    decide($this, ApplicationOutcomeStatus::Rejected);
    app(UndoApplicationOutcome::class)($this->application, $this->manager);
    $this->travel(Application::UNDO_MINUTES + 1)->minutes();

    undoPage($this, $this->manager)->assertSee('undid the rejected decision');

    $this->actingAs($this->application->candidateProfile->user)
        ->get(route('candidate.applications.show', $this->application))
        ->assertOk()
        ->assertDontSee('Rejected')
        ->assertDontSee('marked this application as');
});

test('an undone decision counts for nothing in the analytics', function () {
    decide($this, ApplicationOutcomeStatus::Hired);
    app(UndoApplicationOutcome::class)($this->application, $this->manager);

    $rejected = Application::factory()->for($this->job)->create();
    app(ChangeApplicationOutcome::class)($rejected, $this->manager, ApplicationOutcomeStatus::Rejected);
    app(UndoApplicationOutcome::class)($rejected, $this->manager);

    $report = app(JobPerformance::class)->for($this->company, $this->job);

    expect($report->hires)->toBe(0)
        ->and($report->funnel['hired'])->toBe(0)
        ->and($report->timeToFillDays)->toBe([])
        ->and($report->rejectedUnseen)->toBe(0)
        ->and($report->responded)->toBe(0)
        ->and(JobPerformance::firstResponses(Application::query()->where('job_posting_id', $this->job->id)))->toBeEmpty();
});

test('a decision that stands still counts once the undo window has passed', function () {
    decide($this, ApplicationOutcomeStatus::Hired);

    $report = app(JobPerformance::class)->for($this->company, $this->job);

    expect($report->hires)->toBe(1)
        ->and($report->funnel['hired'])->toBe(1)
        ->and($report->responded)->toBe(1);
});

test('to the candidate, an application stays open until the undo window has passed', function () {
    decide($this, ApplicationOutcomeStatus::Rejected);
    $candidate = $this->application->candidateProfile->user;

    $closed = fn () => $this->actingAs($candidate)->get(route('candidate.dashboard'))->viewData('statusCounts')['closed'];
    $closedList = fn () => $this->actingAs($candidate)->get(route('candidate.applications.index', ['status' => 'closed']))->viewData('applications');

    expect($closed())->toBe(0)->and($closedList())->toHaveCount(0);

    $this->travel(Application::UNDO_MINUTES + 1)->minutes();

    expect($closed())->toBe(1)->and($closedList())->toHaveCount(1);
});

test('an application whose decision was undone is waiting again', function () {
    decide($this, ApplicationOutcomeStatus::Rejected);
    app(UndoApplicationOutcome::class)($this->application, $this->manager);

    expect(app(JobPerformance::class)->for($this->company, $this->job)->waiting)->toBe(1);
});

test('a candidate who deletes their account inside the window is not written to', function () {
    decide($this, ApplicationOutcomeStatus::Rejected);
    $this->application->candidateProfile->user->delete();

    $this->travel(Application::UNDO_MINUTES + 1)->minutes();
    runQueue($this);

    Event::assertNotDispatched(NotificationSent::class);
});
