<?php

use App\Actions\MakeModerator;
use App\Actions\RemoveModerator;
use App\Actions\SuspendUser;
use App\Enums\AccountStatus;
use App\Enums\ModerationAction;
use App\Enums\StaffRole;
use App\Filament\Resources\ModerationEvents\Pages\ListModerationEvents;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\ModerationEvent;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

it('lets a super admin make someone a moderator once they confirm their password', function () {
    $admin = staffWithTwoFactor(StaffRole::SuperAdmin);
    $target = User::factory()->withTwoFactor()->create();
    $this->actingAs($admin);

    Livewire::test(ManageUsers::class)
        ->callAction(TestAction::make('makeModerator')->table($target))
        ->assertHasActionErrors(['current_password' => 'required']);

    expect($target->fresh()->staff_role)->toBeNull();

    Livewire::test(ManageUsers::class)
        ->callAction(TestAction::make('makeModerator')->table($target), data: ['current_password' => 'password'])
        ->assertHasNoActionErrors();

    $event = ModerationEvent::sole();

    expect($target->fresh()->staff_role)->toBe(StaffRole::Moderator)
        ->and($event->action)->toBe(ModerationAction::GrantModerator)
        ->and($event->admin_id)->toBe($admin->id)
        ->and($event->subject->is($target))->toBeTrue();

    $this->actingAs($target->fresh())->get('/admin')->assertSuccessful();
});

it('sends a new moderator without two-factor to set it up first', function () {
    $target = User::factory()->create();
    app(MakeModerator::class)($target, staffWithTwoFactor(StaffRole::SuperAdmin));

    $this->actingAs($target->fresh())->get('/admin')->assertRedirect(route('security.edit'));
});

it('takes the role away with a reason, and the panel closes on the next request', function () {
    $admin = staffWithTwoFactor(StaffRole::SuperAdmin);
    $moderator = staffWithTwoFactor(StaffRole::Moderator);
    $this->actingAs($admin);

    Livewire::test(ManageUsers::class)
        ->callAction(TestAction::make('removeModerator')->table($moderator), data: ['current_password' => 'password'])
        ->assertHasActionErrors(['reason' => 'required']);

    Livewire::test(ManageUsers::class)
        ->callAction(TestAction::make('removeModerator')->table($moderator), data: [
            'reason' => 'Left the team.',
            'current_password' => 'password',
        ])
        ->assertHasNoActionErrors();

    expect($moderator->fresh()->staff_role)->toBeNull()
        ->and(ModerationEvent::sole()->action)->toBe(ModerationAction::RevokeModerator)
        ->and(ModerationEvent::sole()->reason)->toBe('Left the team.');

    $this->actingAs($moderator->fresh())->get('/admin')->assertForbidden();
});

it('never hands out or takes away the super admin role in the panel', function () {
    $admin = staffWithTwoFactor(StaffRole::SuperAdmin);
    $peer = staffWithTwoFactor(StaffRole::SuperAdmin);
    $this->actingAs($admin);

    Livewire::test(ManageUsers::class)
        ->assertActionHidden(TestAction::make('makeModerator')->table($peer))
        ->assertActionHidden(TestAction::make('removeModerator')->table($peer))
        ->assertActionHidden(TestAction::make('makeModerator')->table($admin))
        ->assertActionHidden(TestAction::make('removeModerator')->table($admin));
});

it('only offers the role to someone who can hold it', function (Closure $make) {
    $admin = staffWithTwoFactor(StaffRole::SuperAdmin);
    $target = $make();

    expect($admin->can('makeModerator', $target))->toBeFalse();
})->with([
    'already a moderator' => [fn () => staffWithTwoFactor(StaffRole::Moderator)],
    'suspended' => [fn () => User::factory()->create(['account_status' => AccountStatus::Suspended])],
    'deleted' => [fn () => tap(User::factory()->create())->delete()],
    'erased' => [fn () => User::factory()->create(['anonymized_at' => now()])],
    'unverified email' => [fn () => User::factory()->unverified()->create()],
    'no password' => [fn () => User::factory()->create(['password' => null])],
]);

it('keeps role changes away from moderators', function () {
    $moderator = staffWithTwoFactor(StaffRole::Moderator);

    expect($moderator->can('makeModerator', User::factory()->create()))->toBeFalse()
        ->and($moderator->can('removeModerator', staffWithTwoFactor(StaffRole::Moderator)))->toBeFalse();
});

it('keeps a former moderator in the log filter, with their decisions', function () {
    $admin = staffWithTwoFactor(StaffRole::SuperAdmin);
    $moderator = staffWithTwoFactor(StaffRole::Moderator);
    $suspended = User::factory()->create();
    app(SuspendUser::class)($suspended, $moderator, 'Spam.');
    app(RemoveModerator::class)($moderator, $admin, 'Left the team.');

    $this->actingAs($admin);

    Livewire::test(ListModerationEvents::class)
        ->filterTable('admin_id', $moderator->id)
        ->assertCanSeeTableRecords(ModerationEvent::query()->where('admin_id', $moderator->id)->get())
        ->assertCountTableRecords(1);
});
