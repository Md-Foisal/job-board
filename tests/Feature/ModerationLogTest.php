<?php

use App\Actions\ApproveJobPosting;
use App\Actions\EraseUserData;
use App\Actions\RejectJobPosting;
use App\Enums\ModerationAction;
use App\Enums\StaffRole;
use App\Filament\Resources\ModerationEvents\Pages\ListModerationEvents;
use App\Models\JobPosting;
use App\Models\ModerationEvent;
use Livewire\Livewire;

it('is open to every member of staff, moderators included, and to nobody else', function () {
    $this->get('/admin/moderation/log')->assertRedirect(route('login'));

    $this->actingAs(staffWithTwoFactor(StaffRole::Moderator))
        ->get('/admin/moderation/log')
        ->assertOk();

    $this->actingAs(candidateUser())
        ->get('/admin/moderation/log')
        ->assertForbidden();

    $this->actingAs(employerUser())
        ->get('/admin/moderation/log')
        ->assertForbidden();
});

it('says plainly when nothing has been decided yet', function () {
    $this->actingAs(staffWithTwoFactor());

    Livewire::test(ListModerationEvents::class)
        ->assertSee('No decisions yet');
});

it('lists decisions newest first and filters them by kind', function () {
    $staff = staffWithTwoFactor();
    $approved = app(ApproveJobPosting::class)(JobPosting::factory()->pendingModeration()->create(), $staff);
    $this->travel(1)->minutes();
    $rejected = app(RejectJobPosting::class)(JobPosting::factory()->pendingModeration()->create(), $staff, 'Misleading pay.');
    $this->actingAs($staff);

    Livewire::test(ListModerationEvents::class)
        ->assertCanSeeTableRecords([$rejected, $approved], inOrder: true)
        ->filterTable('action', ModerationAction::RejectJobPosting->value)
        ->assertCanSeeTableRecords([$rejected])
        ->assertCanNotSeeTableRecords([$approved]);
});

it('refuses to let anything change or remove a recorded decision', function () {
    $event = app(ApproveJobPosting::class)(JobPosting::factory()->pendingModeration()->create(), staffWithTwoFactor());

    expect(fn () => $event->update(['reason' => 'rewritten']))->toThrow(LogicException::class)
        ->and(fn () => $event->delete())->toThrow(LogicException::class)
        ->and(ModerationEvent::whereKey($event->id)->value('reason'))->toBeNull();
});

it('names an erased person as they are now, not as something gone', function () {
    $admin = staffWithTwoFactor(StaffRole::SuperAdmin);
    app(EraseUserData::class)(candidateUser(), $admin, 'Asked by email.');

    $this->actingAs($admin);

    Livewire::test(ListModerationEvents::class)
        ->assertSee('Person: Deleted user')
        ->assertDontSee('No longer exists');
});
