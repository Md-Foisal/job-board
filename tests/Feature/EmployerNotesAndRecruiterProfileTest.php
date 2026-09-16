<?php

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Application;
use App\Models\ApplicationNote;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Membership;
use App\Models\RecruiterProfile;
use App\Models\User;

/**
 * Builds the smallest world an application note needs: a company, a job it
 * posted, and somebody's application to that job.
 */
function applicationAtCompany(Company $company): Application
{
    $job = JobPosting::factory()->for($company)->create();
    $candidate = candidateUser();

    return Application::factory()->create([
        'job_posting_id' => $job->id,
        'candidate_profile_id' => $candidate->candidateProfile->id,
    ]);
}

test('anyone working at the posting company may read and write notes', function () {
    $company = Company::factory()->create();
    $application = applicationAtCompany($company);
    $member = employerUser($company);

    expect($member->can('viewAny', [ApplicationNote::class, $application]))->toBeTrue();
    expect($member->can('create', [ApplicationNote::class, $application]))->toBeTrue();
});

test('someone from another company may not read or write notes', function () {
    $application = applicationAtCompany(Company::factory()->create());
    $outsider = employerUser();

    expect($outsider->can('viewAny', [ApplicationNote::class, $application]))->toBeFalse();
    expect($outsider->can('create', [ApplicationNote::class, $application]))->toBeFalse();
});

test('a candidate may not read notes about their own application', function () {
    $company = Company::factory()->create();
    $job = JobPosting::factory()->for($company)->create();
    $candidate = candidateUser();
    $application = Application::factory()->create([
        'job_posting_id' => $job->id,
        'candidate_profile_id' => $candidate->candidateProfile->id,
    ]);

    expect($candidate->can('viewAny', [ApplicationNote::class, $application]))->toBeFalse();
});

test('a note may only be edited by the person who wrote it', function () {
    $company = Company::factory()->create();
    $application = applicationAtCompany($company);
    $author = employerUser($company);
    $colleague = employerUser($company, MembershipRole::Manager);

    $note = ApplicationNote::factory()->create([
        'application_id' => $application->id,
        'author_id' => $author->id,
    ]);

    expect($author->can('update', $note))->toBeTrue();
    expect($author->can('delete', $note))->toBeTrue();

    // A manager outranks the author everywhere else, but a note is one
    // person's own assessment -- rank does not open it.
    expect($colleague->can('update', $note))->toBeFalse();
    expect($colleague->can('delete', $note))->toBeFalse();
});

test('leaving the company closes the door on your own notes', function () {
    $company = Company::factory()->create();
    $application = applicationAtCompany($company);

    $author = User::factory()->create();
    $membership = Membership::factory()->for($author)->for($company)->create();

    $note = ApplicationNote::factory()->create([
        'application_id' => $application->id,
        'author_id' => $author->id,
    ]);

    expect($author->can('update', $note))->toBeTrue();

    $membership->update(['status' => MembershipStatus::Inactive]);

    expect($author->fresh()->can('update', $note))->toBeFalse();
});

test('a recruiter profile may only be changed by the user it belongs to', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $profile = RecruiterProfile::factory()->for($owner)->create();

    expect($owner->can('update', $profile))->toBeTrue();
    expect($owner->can('delete', $profile))->toBeTrue();
    expect($stranger->can('update', $profile))->toBeFalse();
    expect($stranger->can('delete', $profile))->toBeFalse();
});
