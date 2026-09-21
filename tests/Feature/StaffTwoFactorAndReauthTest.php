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
    $this->actingAs(User::factory()->withTwoFactor()->create())
        ->get('/admin')
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertForbidden();
});

it('will not let staff switch two-factor off or swap its secret through Fortify\'s own routes', function () {
    $staff = staffWithTwoFactor();
    $this->actingAs($staff)->withSession(['auth.password_confirmed_at' => time()]);

    $this->deleteJson('/user/two-factor-authentication')->assertForbidden();
    $this->postJson('/user/two-factor-authentication', ['force' => true])->assertForbidden();

    expect($staff->fresh()->two_factor_confirmed_at)->not->toBeNull();
});

it('still lets staff abandon a two-factor setup they never confirmed', function () {
    $staff = staffUser();
    $staff->forceFill(['two_factor_secret' => encrypt('secret')])->save();
    $this->actingAs($staff)->withSession(['auth.password_confirmed_at' => time()]);

    $this->deleteJson('/user/two-factor-authentication')->assertOk();

    expect($staff->fresh()->two_factor_secret)->toBeNull();
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

it('signs staff out of the panel to the home page, so the next person to sign in is not sent to the panel', function () {
    $this->actingAs(staffWithTwoFactor())
        ->post('/admin/logout')
        ->assertRedirect('/');

    $this->assertGuest();
    expect(session('url.intended'))->toBeNull();
});
