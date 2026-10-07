<?php

use App\Actions\ChangeApplicationOutcome;
use App\Enums\ApplicationOutcomeStatus;
use App\Enums\ApplicationStage;
use App\Enums\MembershipRole;
use App\Models\Application;
use App\Models\Company;
use App\Models\JobPosting;
use App\Notifications\ApplicationOutcomeDecided;
use App\Notifications\ApplicationStageChanged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->company = Company::factory()->create();
    $this->job = JobPosting::factory()->for($this->company)->create(['title' => 'Backend Developer']);
    $this->application = Application::factory()->for($this->job)->create([
        'candidate_profile_id' => candidateUser()->candidateProfile->id,
    ]);
});

function detailPage($test, $user)
{
    return Livewire::actingAs($user)->test('pages::employer.application-detail', [
        'company' => $test->company,
        'application' => $test->application,
    ]);
}

test('a manager can hire, and the decision is recorded and told to the candidate', function (MembershipRole $role) {
    $manager = employerUser($this->company, $role);

    detailPage($this, $manager)->call('decide', ApplicationOutcomeStatus::Hired->value)->assertHasNoErrors();

    $application = $this->application->fresh();
    $event = $application->events()->sole();

    expect($application->outcome_status)->toBe(ApplicationOutcomeStatus::Hired)
        ->and($event->from_outcome_status)->toBe('active')
        ->and($event->to_outcome_status)->toBe('hired')
        ->and($event->changed_by_id)->toBe($manager->id);

    Notification::assertSentTo($application->candidateProfile->user, ApplicationOutcomeDecided::class);
})->with([MembershipRole::Owner, MembershipRole::Manager]);

test('a rejection is never quiet: the candidate is always told, after the undo window', function () {
    $this->freezeSecond();

    detailPage($this, employerUser($this->company, MembershipRole::Manager))
        ->call('decide', ApplicationOutcomeStatus::Rejected->value);

    $candidate = $this->application->candidateProfile->user;

    expect($this->application->fresh()->outcome_status)->toBe(ApplicationOutcomeStatus::Rejected);

    Notification::assertSentTo($candidate, ApplicationOutcomeDecided::class, function ($notification) use ($candidate) {
        $mail = $notification->toMail($candidate);

        return $notification instanceof ShouldQueue
            && $notification->delay->equalTo(now()->addMinutes(Application::UNDO_MINUTES))
            && $mail->subject === 'Your application for Backend Developer at '.$this->company->name
            && $mail->introLines === [
                'Thank you for applying for Backend Developer at '.$this->company->name.'. They have reviewed your application and decided not to move forward with it.',
                'This does not affect any of your other applications, and you can keep applying to jobs here.',
            ];
    });
});

test('the hire email says so plainly', function () {
    $candidate = $this->application->candidateProfile->user;

    $mail = (new ApplicationOutcomeDecided($this->application, ApplicationOutcomeStatus::Hired, now()))->toMail($candidate);

    expect($mail->subject)->toBe('Good news from '.$this->company->name)
        ->and($mail->introLines[0])->toContain('as hired')
        ->and($mail->actionUrl)->toBe(route('candidate.applications.show', $this->application));
});

test('a plain member can move stages but cannot decide', function () {
    detailPage($this, employerUser($this->company))
        ->assertDontSee('Mark as hired')
        ->call('decide', ApplicationOutcomeStatus::Rejected->value)
        ->assertForbidden();

    expect($this->application->fresh()->outcome_status)->toBe(ApplicationOutcomeStatus::Active);
    Notification::assertNothingSent();
});

test('the decision buttons are shown to a manager on an open application only', function () {
    $manager = employerUser($this->company, MembershipRole::Manager);

    detailPage($this, $manager)->assertSee('Mark as hired')->assertSee('Reject');

    $this->application->update(['outcome_status' => ApplicationOutcomeStatus::Withdrawn]);

    detailPage($this, $manager)
        ->assertDontSee('Mark as hired')
        ->assertSee('This application is closed (Withdrawn).');
});

test('an application that already has an outcome cannot be decided again', function (ApplicationOutcomeStatus $outcome) {
    $this->application->update(['outcome_status' => $outcome]);

    detailPage($this, employerUser($this->company, MembershipRole::Owner))
        ->call('decide', ApplicationOutcomeStatus::Rejected->value)
        ->assertForbidden();

    expect($this->application->fresh()->outcome_status)->toBe($outcome)
        ->and($this->application->events()->count())->toBe(0);
    Notification::assertNothingSent();
})->with([ApplicationOutcomeStatus::Withdrawn, ApplicationOutcomeStatus::Hired, ApplicationOutcomeStatus::Rejected]);

test('the action itself refuses a closed application and anything but hire or reject', function () {
    $owner = employerUser($this->company, MembershipRole::Owner);
    $decide = app(ChangeApplicationOutcome::class);

    expect(fn () => $decide($this->application, $owner, ApplicationOutcomeStatus::Withdrawn))
        ->toThrow(InvalidArgumentException::class);

    $this->application->update(['outcome_status' => ApplicationOutcomeStatus::Withdrawn]);

    expect($decide($this->application, $owner, ApplicationOutcomeStatus::Hired))->toBeNull()
        ->and($this->application->fresh()->outcome_status)->toBe(ApplicationOutcomeStatus::Withdrawn);
});

test('the page accepts only hire or reject', function (string $outcome) {
    detailPage($this, employerUser($this->company, MembershipRole::Owner))
        ->call('decide', $outcome)
        ->assertStatus(422);

    expect($this->application->fresh()->outcome_status)->toBe(ApplicationOutcomeStatus::Active);
})->with(['withdrawn', 'active', 'nonsense']);

test('someone from another company cannot decide', function () {
    $this->actingAs(employerUser(null, MembershipRole::Owner));

    expect(auth()->user()->can('decideOutcome', $this->application))->toBeFalse();
});

test('a candidate who has deleted their account is not written to', function () {
    $candidate = $this->application->candidateProfile->user;
    $candidate->delete();

    app(ChangeApplicationOutcome::class)($this->application->fresh(), employerUser($this->company, MembershipRole::Owner), ApplicationOutcomeStatus::Rejected);

    expect($this->application->fresh()->outcome_status)->toBe(ApplicationOutcomeStatus::Rejected);
    Notification::assertNothingSent();
});

test('once decided, the stage stops moving, so nobody is told they moved forward after a no', function () {
    $this->application->update(['outcome_status' => ApplicationOutcomeStatus::Rejected]);

    detailPage($this, employerUser($this->company))
        ->set('stage', ApplicationStage::Interview->value)
        ->call('updateStage')
        ->assertForbidden();

    expect($this->application->fresh()->stage)->toBe(ApplicationStage::New);
    Notification::assertNotSentTo($this->application->candidateProfile->user, ApplicationStageChanged::class);
});

test('a batch move leaves closed applications where they are', function () {
    $open = Application::factory()->for($this->job)->create();
    $this->application->update(['outcome_status' => ApplicationOutcomeStatus::Rejected]);

    Livewire::actingAs(employerUser($this->company, MembershipRole::Manager))
        ->test('pages::employer.applications', ['company' => $this->company, 'jobPosting' => $this->job])
        ->set('selected', [$open->id, $this->application->id])
        ->call('moveSelected', ApplicationStage::Shortlisted->value);

    expect($open->fresh()->stage)->toBe(ApplicationStage::Shortlisted)
        ->and($this->application->fresh()->stage)->toBe(ApplicationStage::New)
        ->and($this->application->events()->count())->toBe(0);
});

test('the candidate sees the decision only once the undo window has passed', function () {
    app(ChangeApplicationOutcome::class)($this->application, employerUser($this->company, MembershipRole::Owner), ApplicationOutcomeStatus::Rejected);
    $candidate = $this->application->candidateProfile->user;
    $line = $this->company->name.' decided not to move forward with your application';

    $this->actingAs($candidate)
        ->get(route('candidate.applications.show', $this->application))
        ->assertOk()
        ->assertDontSee($line)
        ->assertDontSee('Not selected');

    $this->travel(Application::UNDO_MINUTES + 1)->minutes();

    $this->actingAs($candidate)
        ->get(route('candidate.applications.show', $this->application))
        ->assertSee($line);

    $this->actingAs($candidate)
        ->get(route('candidate.applications.index', ['status' => 'closed']))
        ->assertSee('Not selected');
});

test('a turned-down or withdrawn application at New is no longer waiting on the company', function () {
    Application::factory()->for($this->job)->create(['outcome_status' => ApplicationOutcomeStatus::Withdrawn]);
    app(ChangeApplicationOutcome::class)($this->application, employerUser($this->company, MembershipRole::Owner), ApplicationOutcomeStatus::Rejected);
    Application::factory()->count(2)->for($this->job)->create();

    $this->actingAs(employerUser($this->company))
        ->get(route('employer.dashboard', $this->company))
        ->assertOk()
        ->assertViewHas('newApplicationCount', 2);

    Livewire::actingAs(employerUser($this->company))
        ->test('pages::employer.job-listings', ['company' => $this->company])
        ->assertSee('(2 new)')
        ->assertDontSee('(3 new)');
});
