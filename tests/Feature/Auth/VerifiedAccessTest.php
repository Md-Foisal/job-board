<?php

use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\User;
use App\Notifications\EmailVerificationLink;
use App\Notifications\PasswordResetLink;
use Illuminate\Auth\Events\Registered;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;

test('someone who has not confirmed their email is asked to before applying', function () {
    $candidate = User::factory()->unverified()->create();
    CandidateProfile::factory()->for($candidate)->create();

    $this->actingAs($candidate)
        ->get(route('jobs.apply', JobPosting::factory()->create()))
        ->assertRedirect(route('verification.notice'));
});

test('once the email is confirmed the same apply page opens', function () {
    $this->actingAs(candidateUser())
        ->get(route('jobs.apply', JobPosting::factory()->create()))
        ->assertOk();
});

test('someone who has not confirmed their email cannot open a company workspace', function () {
    $company = Company::factory()->create();
    $employer = employerUser($company);
    $employer->forceFill(['email_verified_at' => null])->save();

    $this->actingAs($employer)
        ->get(route('employer.dashboard', $company))
        ->assertRedirect(route('verification.notice'));
});

test('an unconfirmed person can still reach their profile, to fix the address or ask for a new link', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('profile.edit'))
        ->assertOk();
});

test('the confirmation link is queued when someone registers', function () {
    Queue::fake();

    event(new Registered(User::factory()->unverified()->create()));

    Queue::assertPushed(
        SendQueuedNotifications::class,
        fn ($job) => $job->notification instanceof EmailVerificationLink,
    );
});

test('the password reset link is queued, so asking looks the same for every address', function () {
    Queue::fake();

    User::factory()->create()->sendPasswordResetNotification('a-token');

    Queue::assertPushed(
        SendQueuedNotifications::class,
        fn ($job) => $job->notification instanceof PasswordResetLink,
    );
});
