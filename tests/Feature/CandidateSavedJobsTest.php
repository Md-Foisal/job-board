<?php

use App\Models\JobPosting;

test('guest is redirected to login', function () {
    $response = $this->get(route('candidate.saved-jobs.index'));

    $response->assertRedirect(route('login'));
});

test('employer cannot view the candidate saved jobs page', function () {
    $employer = employerUser();

    $response = $this->actingAs($employer)->get(route('candidate.saved-jobs.index'));

    $response->assertForbidden();
});

test('candidate sees jobs they saved', function () {
    $candidate = candidateUser();
    $job = JobPosting::factory()->create(['title' => 'Senior Laravel Developer']);
    $candidate->savedJobs()->attach($job->id);

    $response = $this->actingAs($candidate)->get(route('candidate.saved-jobs.index'));

    $response->assertOk();
    $response->assertSee('Senior Laravel Developer');
});

test('candidate does not see jobs they have not saved', function () {
    $candidate = candidateUser();
    JobPosting::factory()->create(['title' => 'Not Saved Role']);

    $response = $this->actingAs($candidate)->get(route('candidate.saved-jobs.index'));

    $response->assertOk();
    $response->assertDontSee('Not Saved Role');
});

test('shows an empty state when the candidate has not saved anything', function () {
    $candidate = candidateUser();

    $response = $this->actingAs($candidate)->get(route('candidate.saved-jobs.index'));

    $response->assertOk();
    $response->assertSee("haven't saved");
});
