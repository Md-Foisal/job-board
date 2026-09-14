<?php

use App\Enums\ApplicationOutcomeStatus;
use App\Models\Application;
use App\Models\JobPosting;

test('guest is redirected to login', function () {
    $response = $this->get(route('candidate.applications.index'));

    $response->assertRedirect(route('login'));
});

test('employer cannot view the candidate applications page', function () {
    $employer = employerUser();

    $response = $this->actingAs($employer)->get(route('candidate.applications.index'));

    $response->assertForbidden();
});

test('candidate sees their own applications', function () {
    $candidate = candidateUser();
    $job = JobPosting::factory()->create(['title' => 'Senior Laravel Developer']);

    Application::factory()->create([
        'job_posting_id' => $job->id,
        'candidate_profile_id' => $candidate->candidateProfile->id,
    ]);

    $response = $this->actingAs($candidate)->get(route('candidate.applications.index'));

    $response->assertOk();
    $response->assertSee('Senior Laravel Developer');
    $response->assertSee(ApplicationOutcomeStatus::Active->label());
});

test('candidate does not see another candidate\'s applications', function () {
    $candidate = candidateUser();
    $other = candidateUser();
    $job = JobPosting::factory()->create(['title' => 'Unrelated Backend Role']);

    Application::factory()->create([
        'job_posting_id' => $job->id,
        'candidate_profile_id' => $other->candidateProfile->id,
    ]);

    $response = $this->actingAs($candidate)->get(route('candidate.applications.index'));

    $response->assertOk();
    $response->assertDontSee('Unrelated Backend Role');
});

test('shows an empty state when the candidate has not applied anywhere', function () {
    $candidate = candidateUser();

    $response = $this->actingAs($candidate)->get(route('candidate.applications.index'));

    $response->assertOk();
    $response->assertSee("haven't applied");
});
