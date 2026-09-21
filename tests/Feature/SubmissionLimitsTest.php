<?php

use App\Enums\DocumentType;
use App\Enums\MembershipRole;
use App\Models\Application;
use App\Models\Company;
use App\Models\Document;
use App\Models\Invitation;
use App\Models\JobPosting;
use App\Models\User;
use App\Support\SubmissionLimits;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

function useUp(string $key, int $times): void
{
    foreach (range(1, $times) as $_) {
        RateLimiter::hit($key, 3600);
    }
}

function registrationPayload(string $email): array
{
    return [
        'name' => 'Someone',
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'candidate',
    ];
}

test('one network cannot keep creating accounts', function () {
    useUp(SubmissionLimits::registrationKey('127.0.0.1'), SubmissionLimits::REGISTRATIONS_PER_HOUR);

    $this->post(route('register.store'), registrationPayload('late@example.com'))
        ->assertSessionHasErrors('email');

    expect(User::where('email', 'late@example.com')->exists())->toBeFalse();
});

test('another network can still sign up while one is limited', function () {
    useUp(SubmissionLimits::registrationKey('10.0.0.1'), SubmissionLimits::REGISTRATIONS_PER_HOUR);

    $this->post(route('register.store'), registrationPayload('fresh@example.com'))
        ->assertSessionHasNoErrors();

    expect(User::where('email', 'fresh@example.com')->exists())->toBeTrue();
});

test('a sign-up that fails validation uses up nothing', function () {
    $this->post(route('register.store'), [...registrationPayload('not-an-email'), 'email' => 'not-an-email'])
        ->assertSessionHasErrors('email');

    expect(RateLimiter::attempts(SubmissionLimits::registrationKey('127.0.0.1')))->toBe(0);

    $this->post(route('register.store'), registrationPayload('real@example.com'));

    expect(RateLimiter::attempts(SubmissionLimits::registrationKey('127.0.0.1')))->toBe(1);
});

test('a candidate past the daily application cap is told so and nothing is sent', function () {
    $candidate = candidateUser();
    $job = JobPosting::factory()->create();
    $cv = Document::factory()->create([
        'candidate_profile_id' => $candidate->candidateProfile->id,
        'document_type' => DocumentType::Cv,
    ]);

    useUp(SubmissionLimits::applicationKey($candidate), SubmissionLimits::APPLICATIONS_PER_DAY);

    Livewire::actingAs($candidate)
        ->test('pages::job-apply', ['jobPosting' => $job])
        ->set('resumeChoice', (string) $cv->id)
        ->call('submit')
        ->assertNoRedirect()
        ->assertDispatched('toast-show');

    expect(Application::count())->toBe(0);
});

test('each application a candidate sends counts towards their own cap only', function () {
    $candidate = candidateUser();
    $cv = Document::factory()->create([
        'candidate_profile_id' => $candidate->candidateProfile->id,
        'document_type' => DocumentType::Cv,
    ]);

    Livewire::actingAs($candidate)
        ->test('pages::job-apply', ['jobPosting' => JobPosting::factory()->create()])
        ->set('resumeChoice', (string) $cv->id)
        ->call('submit')
        ->assertHasNoErrors();

    expect(RateLimiter::attempts(SubmissionLimits::applicationKey($candidate)))->toBe(1)
        ->and(RateLimiter::attempts(SubmissionLimits::applicationKey(candidateUser())))->toBe(0);
});

test('a company past its daily posting allowance cannot start another', function () {
    $company = Company::factory()->create();
    useUp(SubmissionLimits::jobPostingKey($company), SubmissionLimits::JOB_POSTINGS_PER_DAY);

    fillJobForm(Livewire::actingAs(employerUser($company, MembershipRole::Manager))
        ->test('pages::employer.job-form', ['company' => $company]))
        ->call('saveAndPublish')
        ->assertNoRedirect()
        ->assertDispatched('toast-show');

    expect($company->jobPostings()->count())->toBe(0);
});

test('the allowance belongs to the company, so a second teammate does not get a fresh one', function () {
    $company = Company::factory()->create();

    fillJobForm(Livewire::actingAs(employerUser($company, MembershipRole::Manager))
        ->test('pages::employer.job-form', ['company' => $company]))
        ->call('saveAndPublish');

    fillJobForm(Livewire::actingAs(employerUser($company, MembershipRole::Owner))
        ->test('pages::employer.job-form', ['company' => $company]))
        ->call('save');

    expect(RateLimiter::attempts(SubmissionLimits::jobPostingKey($company)))->toBe(2);
});

test('editing an existing posting is never limited', function () {
    $company = Company::factory()->create();
    $job = JobPosting::factory()->for($company)->create(['title' => 'Old title']);
    useUp(SubmissionLimits::jobPostingKey($company), SubmissionLimits::JOB_POSTINGS_PER_DAY);

    fillJobForm(Livewire::actingAs(employerUser($company, MembershipRole::Manager))
        ->test('pages::employer.job-form', ['company' => $company, 'jobPosting' => $job]), ['title' => 'New title'])
        ->call('saveAndPublish')
        ->assertHasNoErrors();

    expect($job->fresh()->title)->toBe('New title');
});

test('duplicating draws on the same allowance, so it is no way round the limit', function () {
    $company = Company::factory()->create();
    $job = JobPosting::factory()->for($company)->create();
    useUp(SubmissionLimits::jobPostingKey($company), SubmissionLimits::JOB_POSTINGS_PER_DAY);

    Livewire::actingAs(employerUser($company, MembershipRole::Owner))
        ->test('pages::employer.job-listings', ['company' => $company])
        ->call('duplicate', $job->id)
        ->assertNoRedirect()
        ->assertDispatched('toast-show');

    expect($company->jobPostings()->count())->toBe(1);
});

test('a company past its daily invitation allowance sends no more mail', function () {
    Notification::fake();

    $company = Company::factory()->create();
    useUp(SubmissionLimits::invitationKey($company), SubmissionLimits::INVITATIONS_PER_DAY);

    Livewire::actingAs(employerUser($company, MembershipRole::Owner))
        ->test('pages::employer.team', ['company' => $company])
        ->set('inviteEmail', 'someone@example.com')
        ->set('inviteRole', 'member')
        ->call('invite')
        ->assertDispatched('toast-show');

    expect(Invitation::count())->toBe(0);
    Notification::assertNothingSent();
});

test('each invitation sent counts towards the company allowance', function () {
    Notification::fake();

    $company = Company::factory()->create();

    Livewire::actingAs(employerUser($company, MembershipRole::Owner))
        ->test('pages::employer.team', ['company' => $company])
        ->set('inviteEmail', 'someone@example.com')
        ->set('inviteRole', 'member')
        ->call('invite')
        ->assertHasNoErrors();

    expect(RateLimiter::attempts(SubmissionLimits::invitationKey($company)))->toBe(1);
});
