<?php

use App\Actions\ReinstateUser;
use App\Actions\SuspendUser;
use App\Enums\AccountStatus;
use App\Enums\ModerationAction;
use App\Enums\StaffRole;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\ModerationEvent;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

function superAdmin(): User
{
    return staffWithTwoFactor(StaffRole::SuperAdmin);
}

it('keeps people management away from moderators', function () {
    $this->actingAs(staffWithTwoFactor(StaffRole::Moderator))
        ->get('/admin/users')
        ->assertForbidden();
});

it('suspends a person only after the super admin confirms their password', function () {
    $target = User::factory()->create();
    $admin = superAdmin();
    $this->actingAs($admin);

    Livewire::test(ManageUsers::class)
        ->callAction(TestAction::make('suspend')->table($target), data: ['reason' => 'Spam applications.'])
        ->assertHasActionErrors(['current_password' => 'required']);

    Livewire::test(ManageUsers::class)
        ->callAction(TestAction::make('suspend')->table($target), data: [
            'reason' => 'Spam applications.',
            'current_password' => 'password',
        ]);

    $event = ModerationEvent::sole();

    expect($target->fresh()->account_status)->toBe(AccountStatus::Suspended)
        ->and($event->action)->toBe(ModerationAction::SuspendUser)
        ->and($event->admin_id)->toBe($admin->id);
});

it('never offers a super admin the chance to suspend themselves', function () {
    $admin = superAdmin();
    $this->actingAs($admin);

    Livewire::test(ManageUsers::class)
        ->assertActionHidden(TestAction::make('suspend')->table($admin));
});

it('refuses to sign a suspended person in, and says why', function () {
    $user = User::factory()->create();
    app(SuspendUser::class)($user, superAdmin(), 'Scam postings.');

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => EnsureAccountIsActive::MESSAGE]);

    $this->assertGuest();
});

it('reveals nothing about a suspension to someone without the password', function () {
    $user = User::factory()->create();
    app(SuspendUser::class)($user, superAdmin(), 'Scam postings.');

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);
});

it('signs a person out on their next request once they are suspended', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    app(SuspendUser::class)($user, superAdmin(), 'Harassing candidates.');

    $this->get('/')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => EnsureAccountIsActive::MESSAGE]);

    $this->assertGuest();
});

it('lets a reinstated person sign in again', function () {
    $user = User::factory()->create();
    $admin = superAdmin();
    app(SuspendUser::class)($user, $admin, 'Mistake.');
    app(ReinstateUser::class)($user, $admin);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $this->assertAuthenticatedAs($user->fresh());
});
