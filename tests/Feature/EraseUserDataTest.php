<?php

use App\Actions\AnonymizeUser;
use App\Enums\ModerationAction;
use App\Enums\StaffRole;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\ModerationEvent;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

function eraser(): User
{
    return staffWithTwoFactor(StaffRole::SuperAdmin);
}

it('erases a person on request, once it is acknowledged and the password confirmed', function () {
    $target = candidateUser();
    $admin = eraser();
    $this->actingAs($admin);

    Livewire::test(ManageUsers::class)
        ->callAction(TestAction::make('erase')->table($target), data: ['reason' => 'Asked by email, ticket 42.'])
        ->assertHasActionErrors(['understood' => 'accepted', 'current_password' => 'required']);

    expect(User::find($target->id)->anonymized_at)->toBeNull();

    Livewire::test(ManageUsers::class)
        ->callAction(TestAction::make('erase')->table($target), data: [
            'reason' => 'Asked by email, ticket 42.',
            'understood' => true,
            'current_password' => 'password',
        ])
        ->assertHasNoActionErrors();

    $erased = User::withTrashed()->find($target->id);
    $event = ModerationEvent::sole();

    expect($erased->anonymized_at)->not->toBeNull()
        ->and($erased->name)->toBe('Deleted user')
        ->and($event->action)->toBe(ModerationAction::EraseUser)
        ->and($event->admin_id)->toBe($admin->id)
        ->and($event->subject_id)->toBe($target->id);
});

it('reaches someone who already deleted their own account and is waiting out the grace period', function () {
    $target = candidateUser();
    $target->delete();
    $this->actingAs(eraser());

    Livewire::test(ManageUsers::class)
        ->assertCanNotSeeTableRecords([$target])
        ->filterTable('trashed', true)
        ->assertCanSeeTableRecords([$target])
        ->callAction(TestAction::make('erase')->table($target), data: [
            'reason' => 'Asked by email after deleting the account.',
            'understood' => true,
            'current_password' => 'password',
        ])
        ->assertHasNoActionErrors();

    expect(User::withTrashed()->find($target->id)->anonymized_at)->not->toBeNull();
});

it('is never offered against oneself, a peer, or someone already erased', function () {
    $admin = eraser();
    $peer = eraser();
    $erased = candidateUser();
    app(AnonymizeUser::class)($erased);
    $this->actingAs($admin);

    Livewire::test(ManageUsers::class)
        ->assertActionHidden(TestAction::make('erase')->table($admin))
        ->assertActionHidden(TestAction::make('erase')->table($peer))
        ->filterTable('trashed', false)
        ->assertActionHidden(TestAction::make('erase')->table(User::withTrashed()->find($erased->id)));
});

it('keeps deleted accounts off the suspend and reinstate actions', function () {
    $target = candidateUser();
    $target->delete();
    $this->actingAs(eraser());

    Livewire::test(ManageUsers::class)
        ->filterTable('trashed', false)
        ->assertActionHidden(TestAction::make('suspend')->table(User::withTrashed()->find($target->id)))
        ->assertActionHidden(TestAction::make('reinstate')->table(User::withTrashed()->find($target->id)));
});
