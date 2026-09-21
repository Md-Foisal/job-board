<?php

use App\Enums\StaffRole;
use App\Filament\Support\ConfirmsPassword;
use App\Models\User;
use Livewire\Livewire;

it('turns a staff member without two-factor away from the panel, towards setting it up', function () {
    $this->actingAs(staffUser())
        ->get('/admin')
        ->assertRedirect(route('security.edit'));
});

it('lets a staff member with two-factor into the panel', function () {
    $staff = User::factory()->withTwoFactor()->create(['staff_role' => StaffRole::Moderator]);

    $this->actingAs($staff)
        ->get('/admin')
        ->assertOk();
});

it('keeps an ordinary user out of the panel whatever their two-factor state', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

it('explains on the security page why a staff member was sent there', function () {
    $this->actingAs(staffUser());

    Livewire::test('pages::settings.security')
        ->assertSee('Required for staff accounts');
});

it('will not let a staff member switch two-factor off', function () {
    $staff = User::factory()->withTwoFactor()->create(['staff_role' => StaffRole::Moderator]);
    $this->actingAs($staff);

    Livewire::test('pages::settings.security')
        ->assertDontSee('Disable 2FA')
        ->call('disable')
        ->assertForbidden();

    expect($staff->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
});

it('asks for a password before a destructive action, then trusts it for the confirmation window', function () {
    expect(ConfirmsPassword::recentlyConfirmed())->toBeFalse()
        ->and(ConfirmsPassword::fields())->toHaveCount(1);

    ConfirmsPassword::remember();

    expect(ConfirmsPassword::recentlyConfirmed())->toBeTrue()
        ->and(ConfirmsPassword::fields())->toBeEmpty();

    $this->travel(config('auth.password_timeout') + 1)->seconds();

    expect(ConfirmsPassword::recentlyConfirmed())->toBeFalse();
});
