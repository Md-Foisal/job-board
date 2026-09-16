<?php

use App\Actions\ChangeApplicationStage;
use App\Enums\ApplicationStage;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Application;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\ApplicationStageChanged;
use App\Notifications\NewApplicationReceived;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('moving an application forward tells the candidate', function () {
    Notification::fake();

    $company = Company::factory()->create();
    $job = JobPosting::factory()->for($company)->create();
    $candidate = candidateUser();
    $application = Application::factory()->create([
        'job_posting_id' => $job->id,
        'candidate_profile_id' => $candidate->candidateProfile->id,
    ]);

    app(ChangeApplicationStage::class)(
        $application,
        employerUser($company),
        ApplicationStage::Interview,
    );

    Notification::assertSentTo($candidate, ApplicationStageChanged::class);
});

test('a stage that did not actually change sends nothing', function () {
    Notification::fake();

    $company = Company::factory()->create();
    $job = JobPosting::factory()->for($company)->create();
    $candidate = candidateUser();
    $application = Application::factory()->create([
        'job_posting_id' => $job->id,
        'candidate_profile_id' => $candidate->candidateProfile->id,
        'stage' => ApplicationStage::New,
    ]);

    app(ChangeApplicationStage::class)($application, employerUser($company), ApplicationStage::New);

    Notification::assertNothingSent();
});

test('a new application reaches the people who can act on it', function () {
    Notification::fake();

    $company = Company::factory()->create();
    $owner = employerUser($company, MembershipRole::Owner);
    $manager = employerUser($company, MembershipRole::Manager);
    $member = employerUser($company, MembershipRole::Member);
    $outsider = employerUser();

    $job = JobPosting::factory()->for($company)->create([
        'availability_status' => \App\Enums\AvailabilityStatus::Active,
        'moderation_status' => \App\Enums\ModerationStatus::Approved,
    ]);

    $candidate = candidateUser();
    $candidate->candidateProfile->documents()->create([
        'document_type' => \App\Enums\DocumentType::Cv,
        'file_path' => 'resumes/cv.pdf',
        'original_filename' => 'cv.pdf',
    ]);

    Livewire::actingAs($candidate)
        ->test('pages::job-apply', ['jobPosting' => $job])
        ->call('submit')
        ->assertHasNoErrors();

    Notification::assertSentTo($owner, NewApplicationReceived::class);
    Notification::assertSentTo($manager, NewApplicationReceived::class);

    // A plain member cannot move an application along, so telling them
    // would be noise they would learn to ignore.
    Notification::assertNotSentTo($member, NewApplicationReceived::class);
    Notification::assertNotSentTo($outsider, NewApplicationReceived::class);
});

test('someone who has left the company is not told', function () {
    Notification::fake();

    $company = Company::factory()->create();
    $formerOwner = User::factory()->create();
    $membership = Membership::factory()->for($formerOwner)->for($company)->create([
        'role' => MembershipRole::Owner,
    ]);
    $membership->update(['status' => MembershipStatus::Inactive]);

    $job = JobPosting::factory()->for($company)->create();
    $candidate = candidateUser();
    $application = Application::factory()->create([
        'job_posting_id' => $job->id,
        'candidate_profile_id' => $candidate->candidateProfile->id,
    ]);

    event(new \App\Events\ApplicationSubmitted($application));

    Notification::assertNotSentTo($formerOwner, NewApplicationReceived::class);
});
