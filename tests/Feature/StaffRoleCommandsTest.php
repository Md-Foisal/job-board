<?php

use App\Enums\AccountStatus;
use App\Enums\StaffRole;
use App\Models\ModerationEvent;
use App\Models\User;

it('makes an existing account the first super admin', function () {
    $owner = User::factory()->create(['email' => 'owner@example.com']);

    $this->artisan('staff:grant', ['email' => 'owner@example.com', '--role' => 'super_admin'])
        ->expectsOutputToContain('owner@example.com is now a Super Admin.')
        ->expectsOutputToContain('two-factor')
        ->assertSuccessful();

    expect($owner->fresh()->staff_role)->toBe(StaffRole::SuperAdmin)
        // The log holds decisions staff take in the panel; this one was
        // the server owner's.
        ->and(ModerationEvent::query()->exists())->toBeFalse();
});

it('asks for the role when it is not given', function () {
    $user = User::factory()->create(['email' => 'mod@example.com']);

    $this->artisan('staff:grant', ['email' => 'mod@example.com'])
        ->expectsChoice('Which role?', 'moderator', ['moderator' => 'Moderator', 'super_admin' => 'Super Admin'])
        ->assertSuccessful();

    expect($user->fresh()->staff_role)->toBe(StaffRole::Moderator);
});

it('refuses an unknown role', function () {
    User::factory()->create(['email' => 'mod@example.com']);

    $this->artisan('staff:grant', ['email' => 'mod@example.com', '--role' => 'owner'])
        ->expectsOutputToContain('moderator or super_admin')
        ->assertFailed();
});

it('refuses accounts that cannot safely hold a staff role', function (Closure $make, string $reason) {
    $make();

    $this->artisan('staff:grant', ['email' => 'someone@example.com', '--role' => 'moderator'])
        ->expectsOutputToContain($reason)
        ->assertFailed();

    expect(User::withTrashed()->whereNotNull('staff_role')->exists())->toBeFalse();
})->with([
    'no such account' => [fn () => null, 'sign up first'],
    'erased' => [fn () => User::factory()->create(['email' => 'someone@example.com', 'anonymized_at' => now()]), 'erased'],
    'deleted' => [fn () => User::factory()->create(['email' => 'someone@example.com'])->delete(), 'deleted by its owner'],
    'suspended' => [fn () => User::factory()->create(['email' => 'someone@example.com', 'account_status' => AccountStatus::Suspended]), 'suspended'],
    'unverified email' => [fn () => User::factory()->unverified()->create(['email' => 'someone@example.com']), 'not verified'],
    'no password' => [fn () => User::factory()->create(['email' => 'someone@example.com', 'password' => null]), 'no password'],
]);

it('says so when the account already has the role', function () {
    staffWithTwoFactor(StaffRole::Moderator)->update(['email' => 'mod@example.com']);

    $this->artisan('staff:grant', ['email' => 'mod@example.com', '--role' => 'moderator'])
        ->expectsOutputToContain('already a Moderator')
        ->doesntExpectOutputToContain('two-factor')
        ->assertSuccessful();
});

it('takes a staff role away and closes the panel to them', function () {
    $moderator = staffWithTwoFactor(StaffRole::Moderator);
    staffWithTwoFactor(StaffRole::SuperAdmin);

    $this->artisan('staff:revoke', ['email' => $moderator->email])
        ->expectsOutputToContain('no longer a Moderator')
        ->assertSuccessful();

    expect($moderator->fresh()->staff_role)->toBeNull();

    $this->actingAs($moderator->fresh())->get('/admin')->assertForbidden();
});

it('asks before removing the last super admin, and keeps them by default', function () {
    $admin = staffWithTwoFactor(StaffRole::SuperAdmin);

    $this->artisan('staff:revoke', ['email' => $admin->email])
        ->expectsConfirmation('This is the last active super admin. Nobody will be able to manage moderators until another is granted. Continue?', 'no')
        ->assertFailed();

    expect($admin->fresh()->staff_role)->toBe(StaffRole::SuperAdmin);

    $this->artisan('staff:revoke', ['email' => $admin->email])
        ->expectsConfirmation('This is the last active super admin. Nobody will be able to manage moderators until another is granted. Continue?', 'yes')
        ->assertSuccessful();

    expect($admin->fresh()->staff_role)->toBeNull();
});

it('refuses to revoke from an account that is not staff', function () {
    User::factory()->create(['email' => 'plain@example.com']);

    $this->artisan('staff:revoke', ['email' => 'plain@example.com'])
        ->expectsOutputToContain('No staff account')
        ->assertFailed();
});
