<?php

use App\Enums\ApplicationOutcomeStatus;
use App\Enums\ApplicationStage;
use App\Enums\DocumentType;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Application;
use App\Models\Document;
use App\Models\JobPosting;
use Livewire\Livewire;

test('guest is redirected to login when visiting the apply page', function () {
    $job = JobPosting::factory()->create();

    $response = $this->get(route('jobs.apply', $job));

    $response->assertRedirect(route('login'));
});

test('an employer without a candidate profile cannot visit the apply page', function () {
    $job = JobPosting::factory()->create();
    $employer = employerUser();

    $response = $this->actingAs($employer)->get(route('jobs.apply', $job));

    $response->assertForbidden();
});

test('a candidate can view the apply page for an open job', function () {
    $job = JobPosting::factory()->create();
    $candidate = candidateUser();

    $response = $this->actingAs($candidate)->get(route('jobs.apply', $job));

    $response->assertOk();
});

test('a candidate cannot apply to a job at a company they work at', function () {
    $candidate = candidateUser();
    $job = JobPosting::factory()->create();
    $job->company->memberships()->create([
        'user_id' => $candidate->id,
        'role' => MembershipRole::Member,
        'status' => MembershipStatus::Active,
    ]);

    $response = $this->actingAs($candidate)->get(route('jobs.apply', $job));

    $response->assertForbidden();
});

test('a candidate cannot apply to the same job twice', function () {
    $candidate = candidateUser();
    $job = JobPosting::factory()->create();

    Application::factory()->create([
        'job_posting_id' => $job->id,
        'candidate_profile_id' => $candidate->candidateProfile->id,
        'resume_document_id' => Document::factory()->create([
            'candidate_profile_id' => $candidate->candidateProfile->id,
            'document_type' => DocumentType::Cv,
        ])->id,
    ]);

    $response = $this->actingAs($candidate)->get(route('jobs.apply', $job));

    $response->assertForbidden();
});

test('a candidate can submit an application using an existing CV', function () {
    $candidate = candidateUser();
    $job = JobPosting::factory()->create();
    $cv = Document::factory()->create([
        'candidate_profile_id' => $candidate->candidateProfile->id,
        'document_type' => DocumentType::Cv,
    ]);

    $this->actingAs($candidate);

    Livewire::test('pages::job-apply', ['jobPosting' => $job])
        ->set('resumeChoice', (string) $cv->id)
        ->set('coverLetter', 'I would love to join your team.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('jobs.show', $job));

    $application = Application::query()
        ->where('job_posting_id', $job->id)
        ->where('candidate_profile_id', $candidate->candidateProfile->id)
        ->first();

    expect($application)->not->toBeNull();
    expect($application->resume_document_id)->toBe($cv->id);
    expect($application->cover_letter)->toBe('I would love to join your team.');
    expect($application->outcome_status)->toBe(ApplicationOutcomeStatus::Active);
    expect($application->stage)->toBe(ApplicationStage::New);
});
