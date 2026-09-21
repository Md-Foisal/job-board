<?php

use App\Enums\AvailabilityStatus;
use App\Enums\InvitationStatus;
use App\Enums\MembershipRole;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\JobPosting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('open postings past their closing date are marked expired, and nothing else is touched', function () {
    $lapsed = JobPosting::factory()->create(['expires_at' => now()->subMinute()]);
    $open = JobPosting::factory()->create(['expires_at' => now()->addDay()]);
    $closed = JobPosting::factory()->create([
        'availability_status' => AvailabilityStatus::Closed,
        'expires_at' => now()->subDay(),
    ]);
    $draft = JobPosting::factory()->draft()->create(['expires_at' => now()->subDay()]);

    $this->artisan('job-postings:expire')->assertSuccessful();

    expect($lapsed->fresh()->availability_status)->toBe(AvailabilityStatus::Expired)
        ->and($open->fresh()->availability_status)->toBe(AvailabilityStatus::Active)
        ->and($closed->fresh()->availability_status)->toBe(AvailabilityStatus::Closed)
        ->and($draft->fresh()->availability_status)->toBe(AvailabilityStatus::Draft);
});

test('an expired posting shows under the company\'s Expired filter', function () {
    $company = Company::factory()->create();
    $owner = employerUser($company, MembershipRole::Owner);
    JobPosting::factory()->for($company)->create(['title' => 'Lapsed Role', 'expires_at' => now()->subMinute()]);

    $this->artisan('job-postings:expire');

    Livewire::actingAs($owner)
        ->test('pages::employer.job-listings', ['company' => $company])
        ->set('filter', AvailabilityStatus::Expired->value)
        ->assertSee('Lapsed Role');
});

test('expiring postings replaces the cached homepage straight away', function () {
    JobPosting::factory()->create(['title' => 'Closing Soon Role', 'expires_at' => now()->addMinutes(2)]);

    $this->get('/')->assertSee('Closing Soon Role');

    // Past its date but still inside the cache's lifetime: without the
    // flush the homepage would keep advertising it until the cache ran out.
    $this->travel(3)->minutes();
    $this->get('/')->assertSee('Closing Soon Role');

    $this->artisan('job-postings:expire');

    $this->get('/')->assertDontSee('Closing Soon Role');
});

test('pending invitations past their date are marked expired, and nothing else is touched', function () {
    $lapsed = Invitation::factory()->stale()->create();
    $open = Invitation::factory()->create();
    $accepted = Invitation::factory()->accepted()->stale()->create();

    $this->artisan('invitations:expire')->assertSuccessful();

    expect($lapsed->fresh()->status)->toBe(InvitationStatus::Expired)
        ->and($open->fresh()->status)->toBe(InvitationStatus::Pending)
        ->and($accepted->fresh()->status)->toBe(InvitationStatus::Accepted);
});

test('an address whose invitation lapsed can be invited again before the sweep runs', function () {
    Notification::fake();

    $company = Company::factory()->create();
    $owner = employerUser($company, MembershipRole::Owner);
    Invitation::factory()->for($company)->stale()->create(['email' => 'nadia@example.com']);

    Livewire::actingAs($owner)
        ->test('pages::employer.team', ['company' => $company])
        ->set('inviteEmail', 'nadia@example.com')
        ->call('invite')
        ->assertHasNoErrors();

    expect($company->invitations()->where('status', InvitationStatus::Pending)->count())->toBe(2);
});

test('both sweeps are on the schedule every minute', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command ?? '', 'job-postings:expire')
            || str_contains($event->command ?? '', 'invitations:expire'));

    expect($events)->toHaveCount(2)
        ->and($events->every(fn ($event) => $event->expression === '* * * * *'))->toBeTrue();
});
