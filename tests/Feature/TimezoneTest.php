<?php

use App\Models\Application;
use App\Models\Invitation;
use App\Models\JobPosting;
use App\Models\User;
use App\Notifications\TeamMemberInvited;
use App\Support\LocalTime;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/**
 * Karim applied at 17:41 UTC on 2 October, which in Dhaka was 23:41.
 */
function applicationAt(string $utc): Application
{
    return Application::factory()
        ->for(JobPosting::factory())
        ->create([
            'candidate_profile_id' => candidateUser()->candidateProfile->id,
            'created_at' => Carbon::parse($utc, 'UTC'),
        ]);
}

test('the browser\'s zone is saved to an account that follows it', function () {
    $user = candidateUser();

    $this->actingAs($user)
        ->withUnencryptedCookie(LocalTime::COOKIE, 'Asia/Dhaka')
        ->get(route('dashboard'));

    expect($user->fresh()->timezone)->toBe('Asia/Dhaka');
});

test('a zone chosen by hand is not replaced by the browser\'s', function () {
    $user = candidateUser();
    $user->forceFill(['timezone' => 'Europe/London', 'timezone_automatic' => false])->save();

    $this->actingAs($user)
        ->withUnencryptedCookie(LocalTime::COOKIE, 'Asia/Dhaka')
        ->get(route('dashboard'));

    expect($user->fresh()->timezone)->toBe('Europe/London');
});

test('a made-up zone in the cookie is ignored', function () {
    $user = candidateUser();

    $this->actingAs($user)
        ->withUnencryptedCookie(LocalTime::COOKIE, 'Mars/Olympus_Mons')
        ->get(route('dashboard'));

    expect($user->fresh()->timezone)->toBeNull();
});

test('the old names Chrome and Safari still report are kept under their current name', function () {
    $user = candidateUser();

    $this->actingAs($user)
        ->withUnencryptedCookie(LocalTime::COOKIE, 'Asia/Calcutta')
        ->get(route('dashboard'));

    expect($user->fresh()->timezone)->toBe('Asia/Kolkata');
});

test('the application history shows the time in the candidate\'s zone', function () {
    $application = applicationAt('2026-10-02 17:41:00');
    $candidate = $application->candidateProfile->user;
    $candidate->forceFill(['timezone' => 'Asia/Dhaka'])->save();

    $this->actingAs($candidate)
        ->get(route('candidate.applications.show', $application))
        ->assertOk()
        ->assertSee('2 Oct 2026, 11:41 pm')
        ->assertDontSee('5:41 pm');
});

test('without any zone the history stays in UTC', function () {
    $application = applicationAt('2026-10-02 17:41:00');

    $this->actingAs($application->candidateProfile->user)
        ->get(route('candidate.applications.show', $application))
        ->assertSee('2 Oct 2026, 5:41 pm');
});

test('choosing a zone in settings keeps it, and automatic follows the browser again', function () {
    $user = candidateUser();

    Livewire::actingAs($user)
        ->test('pages::settings.profile')
        ->set('timezone', 'America/New_York')
        ->call('updateTimezone')
        ->assertHasNoErrors();

    expect($user->fresh())
        ->timezone->toBe('America/New_York')
        ->timezone_automatic->toBeFalse();

    Livewire::withCookie(LocalTime::COOKIE, 'Asia/Dhaka')
        ->actingAs($user->fresh())
        ->test('pages::settings.profile')
        ->assertSet('timezone', 'America/New_York')
        ->set('timezone', '')
        ->call('updateTimezone')
        ->assertHasNoErrors();

    expect($user->fresh())
        ->timezone->toBe('Asia/Dhaka')
        ->timezone_automatic->toBeTrue();
});

test('settings refuse a zone that does not exist', function () {
    Livewire::actingAs(candidateUser())
        ->test('pages::settings.profile')
        ->set('timezone', 'Mars/Olympus_Mons')
        ->call('updateTimezone')
        ->assertHasErrors('timezone');
});

test('the invitation email gives the expiry in a named zone', function () {
    $inviter = User::factory()->create(['timezone' => 'Asia/Dhaka']);
    $invitation = Invitation::factory()->create([
        'invited_by_id' => $inviter->id,
        'email' => 'nadia@example.com',
        'expires_at' => Carbon::parse('2026-10-09 17:41:00', 'UTC'),
    ]);

    $mail = (new TeamMemberInvited($invitation))->toMail($invitation);

    expect($mail->outroLines[0])->toContain('9 October 2026, 11:41 pm')
        ->toContain('Asia/Dhaka (GMT+06:00)');
});

test('the invitation email uses the invitee\'s own zone when they have an account', function () {
    User::factory()->create(['email' => 'nadia@example.com', 'timezone' => 'Europe/London']);
    $invitation = Invitation::factory()->create([
        'invited_by_id' => User::factory()->create(['timezone' => 'Asia/Dhaka'])->id,
        'email' => 'nadia@example.com',
        'expires_at' => Carbon::parse('2026-10-09 17:41:00', 'UTC'),
    ]);

    $mail = (new TeamMemberInvited($invitation))->toMail($invitation);

    expect($mail->outroLines[0])->toContain('9 October 2026, 6:41 pm')
        ->toContain('Europe/London (GMT+01:00)');
});

test('a zone is labelled with the offset in force on the day shown, not today\'s', function () {
    $this->travelTo(Carbon::parse('2026-10-02 12:00', 'UTC'));

    expect(LocalTime::label('Europe/London'))->toBe('Europe/London (GMT+01:00)')
        ->and(LocalTime::label('Europe/London', at: Carbon::parse('2026-11-02 12:00', 'UTC')))->toBe('Europe/London (GMT+00:00)');
});

test('the staff panel formats times in the staff member\'s zone', function () {
    $this->actingAs(staffUser()->forceFill(['timezone' => 'Asia/Dhaka']));

    expect(FilamentTimezone::get())->toBe('Asia/Dhaka');
});

test('every page carries the script that reports the browser\'s zone', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('resolvedOptions().timeZone', false);
});

test('the privacy policy says the time zone is kept and why', function () {
    $this->get(route('privacy'))
        ->assertSee('Your browser tells us its time zone')
        ->assertSee('so our emails use it too');
});
