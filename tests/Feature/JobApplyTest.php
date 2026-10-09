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

test('an employer without a candidate profile is sent to the job page, which explains how to apply', function () {
    $job = JobPosting::factory()->create();
    $employer = employerUser();

    $this->actingAs($employer)
        ->get(route('jobs.apply', $job))
        ->assertRedirect(route('jobs.show', $job));

    $this->actingAs($employer)
        ->get(route('jobs.show', $job))
        ->assertSee('Start a candidate profile');
});

test('a candidate can view the apply page for an open job', function () {
    $job = JobPosting::factory()->create();
    $candidate = candidateUser();

    $response = $this->actingAs($candidate)->get(route('jobs.apply', $job));

    $response->assertOk();
});

test('the apply page of a posting the public cannot see is a 404, as the posting itself is', function () {
    $this->actingAs(candidateUser())
        ->get(route('jobs.apply', JobPosting::factory()->draft()->create()))
        ->assertNotFound();
});

test('a candidate who works at the company is sent to the job page instead of the form', function () {
    $candidate = candidateUser();
    $job = JobPosting::factory()->create();
    $job->company->memberships()->create([
        'user_id' => $candidate->id,
        'role' => MembershipRole::Member,
        'status' => MembershipStatus::Active,
    ]);

    $response = $this->actingAs($candidate)->get(route('jobs.apply', $job));

    $response->assertRedirect(route('jobs.show', $job));
});

test('a candidate who already applied is sent to the job page, which shows the application', function () {
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

    $response->assertRedirect(route('jobs.show', $job));

    $this->actingAs($candidate)->get(route('jobs.show', $job))->assertSee('You applied on');
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

test('the apply page says what the employer will see and summarises the job', function () {
    $job = JobPosting::factory()->create(['title' => 'Platform Engineer']);

    $this->actingAs(candidateUser())
        ->get(route('jobs.apply', $job))
        ->assertOk()
        ->assertSee('Apply: Platform Engineer')
        ->assertSee('Upload a new CV')
        ->assertSee('Cover letter (optional)')
        ->assertSee('What '.e($job->company->name).' sees', false)
        ->assertSee('Your job preferences and the pay you want stay private.')
        ->assertDontSeeText('Resume');
});

test('applying with a new CV but no file asks for the file in plain words', function () {
    $job = JobPosting::factory()->create();

    Livewire::actingAs(candidateUser())
        ->test('pages::job-apply', ['jobPosting' => $job])
        ->set('resumeChoice', 'new')
        ->call('submit')
        ->assertHasErrors(['newResume' => 'required'])
        ->assertSee('Choose a CV to upload, or pick one you have already uploaded.');
});

test('every screening question needs an answer', function () {
    $job = JobPosting::factory()->create();
    $question = $job->screeningQuestions()->create(['question_text' => 'Why us?', 'display_order' => 0]);
    $candidate = candidateUser();
    $cv = Document::factory()->create([
        'candidate_profile_id' => $candidate->candidateProfile->id,
        'document_type' => DocumentType::Cv,
    ]);

    Livewire::actingAs($candidate)
        ->test('pages::job-apply', ['jobPosting' => $job])
        ->set('resumeChoice', (string) $cv->id)
        ->call('submit')
        ->assertHasErrors(["screeningAnswers.{$question->id}" => 'required'])
        ->assertSee('Answer this question to apply.');

    expect(Application::count())->toBe(0);
});
