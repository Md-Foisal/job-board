<?php

use App\Enums\ApplicationOutcomeStatus;
use App\Enums\ApplicationStage;
use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\User;

/**
 * An application filed by $candidate at a company with a known name, so
 * a test can assert on what the timeline does and does not print.
 */
function timelineApplication(User $candidate, string $companyName = 'Acme Ltd'): Application
{
    $company = Company::factory()->create(['name' => $companyName]);

    return Application::factory()->create([
        'candidate_profile_id' => $candidate->candidateProfile->id,
        'job_posting_id' => JobPosting::factory()->create([
            'company_id' => $company->id,
            'title' => 'Senior Laravel Developer',
        ])->id,
    ]);
}

test('guest is redirected to login', function () {
    $application = timelineApplication(candidateUser());

    $response = $this->get(route('candidate.applications.show', $application));

    $response->assertRedirect(route('login'));
});

test('a candidate sees their own application timeline', function () {
    $candidate = candidateUser();
    $application = timelineApplication($candidate);

    $response = $this->actingAs($candidate)->get(route('candidate.applications.show', $application));

    $response->assertOk();
    $response->assertSee('Senior Laravel Developer');
    $response->assertSee('Acme Ltd');
    // The application itself is always the first entry.
    $response->assertSee('You applied');
});

test("a candidate cannot open another candidate's application", function () {
    $application = timelineApplication(candidateUser());
    $someoneElse = candidateUser();

    $response = $this->actingAs($someoneElse)->get(route('candidate.applications.show', $application));

    $response->assertForbidden();
});

test('an employer cannot open the candidate-side timeline', function () {
    $application = timelineApplication(candidateUser());

    $response = $this->actingAs(employerUser())->get(route('candidate.applications.show', $application));

    $response->assertForbidden();
});

test('stage changes made by the employer appear on the timeline', function () {
    $candidate = candidateUser();
    $application = timelineApplication($candidate);

    ApplicationEvent::create([
        'application_id' => $application->id,
        'changed_by_id' => employerUser($application->jobPosting->company)->id,
        'from_stage' => ApplicationStage::New->value,
        'to_stage' => ApplicationStage::Interview->value,
    ]);

    $response = $this->actingAs($candidate)->get(route('candidate.applications.show', $application));

    $response->assertOk();
    $response->assertSee('Acme Ltd moved your application to Interview');
});

test('the timeline names the company, never the staff member who made the change', function () {
    $candidate = candidateUser();
    $application = timelineApplication($candidate);

    $staff = employerUser($application->jobPosting->company);
    $staff->update(['name' => 'Nadia Karim']);

    ApplicationEvent::create([
        'application_id' => $application->id,
        'changed_by_id' => $staff->id,
        'from_outcome_status' => ApplicationOutcomeStatus::Active->value,
        'to_outcome_status' => ApplicationOutcomeStatus::Rejected->value,
    ]);

    $response = $this->actingAs($candidate)->get(route('candidate.applications.show', $application));

    $response->assertOk();
    $response->assertSee('Acme Ltd marked this application as Rejected');
    // The candidate has no business knowing which
    // individual read their application.
    $response->assertDontSee('Nadia Karim');
});

test('withdrawing marks the application withdrawn and records an event', function () {
    $candidate = candidateUser();
    $application = timelineApplication($candidate);

    $response = $this->actingAs($candidate)
        ->patch(route('candidate.applications.withdraw', $application));

    $response->assertRedirect(route('candidate.applications.show', $application));

    expect($application->fresh()->outcome_status)->toBe(ApplicationOutcomeStatus::Withdrawn);

    $event = ApplicationEvent::where('application_id', $application->id)->sole();
    expect($event->from_outcome_status)->toBe(ApplicationOutcomeStatus::Active->value);
    expect($event->to_outcome_status)->toBe(ApplicationOutcomeStatus::Withdrawn->value);
    expect($event->changed_by_id)->toBe($candidate->id);
});

test("the candidate's own withdrawal reads as their action, not the company's", function () {
    $candidate = candidateUser();
    $application = timelineApplication($candidate);

    $this->actingAs($candidate)->patch(route('candidate.applications.withdraw', $application));

    $response = $this->actingAs($candidate)->get(route('candidate.applications.show', $application));

    $response->assertOk();
    $response->assertSee('You withdrew this application');
});

test('an application that is no longer active cannot be withdrawn again', function () {
    $candidate = candidateUser();
    $application = timelineApplication($candidate);
    $application->update(['outcome_status' => ApplicationOutcomeStatus::Hired]);

    $response = $this->actingAs($candidate)
        ->patch(route('candidate.applications.withdraw', $application));

    $response->assertForbidden();
    expect($application->fresh()->outcome_status)->toBe(ApplicationOutcomeStatus::Hired);
});

test("a candidate cannot withdraw another candidate's application", function () {
    $application = timelineApplication(candidateUser());
    $someoneElse = candidateUser();

    $response = $this->actingAs($someoneElse)
        ->patch(route('candidate.applications.withdraw', $application));

    $response->assertForbidden();
    expect($application->fresh()->outcome_status)->toBe(ApplicationOutcomeStatus::Active);
});

test('My Applications links through to the timeline, not straight to the job', function () {
    $candidate = candidateUser();
    $application = timelineApplication($candidate);

    $response = $this->actingAs($candidate)->get(route('candidate.applications.index'));

    $response->assertOk();
    $response->assertSee(route('candidate.applications.show', $application), false);
});
