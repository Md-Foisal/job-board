<?php

use App\Enums\MembershipRole;
use App\Models\Company;
use App\Models\Membership;
use App\Models\User;
use Livewire\Livewire;

function tryToDelete(User $user)
{
    return Livewire::actingAs($user)
        ->test('pages::settings.delete-user-modal')
        ->set('password', 'password')
        ->call('deleteUser');
}

test('the last owner of a team cannot delete their account and strand the others', function () {
    $company = Company::factory()->create(['name' => 'Shared Studio']);
    $owner = employerUser($company, MembershipRole::Owner);
    employerUser($company, MembershipRole::Member);

    $attempt = tryToDelete($owner)->assertHasErrors('password');

    expect($attempt->errors()->first('password'))->toContain('You are the only owner of Shared Studio');

    expect($owner->fresh()->trashed())->toBeFalse();
});

test('once someone else is an owner too, they can go', function () {
    $company = Company::factory()->create();
    $owner = employerUser($company, MembershipRole::Owner);
    employerUser($company, MembershipRole::Owner);

    tryToDelete($owner)->assertHasNoErrors();

    expect(User::withTrashed()->find($owner->id)->trashed())->toBeTrue();
});

test('someone alone in their company can delete their account', function () {
    $company = Company::factory()->create();
    $owner = employerUser($company, MembershipRole::Owner);

    tryToDelete($owner)->assertHasNoErrors();

    expect(User::withTrashed()->find($owner->id)->trashed())->toBeTrue();
});

test('a member who has left does not count as someone to strand', function () {
    $company = Company::factory()->create();
    $owner = employerUser($company, MembershipRole::Owner);
    Membership::factory()->for($company)->create(['role' => MembershipRole::Member, 'status' => App\Enums\MembershipStatus::Inactive]);

    tryToDelete($owner)->assertHasNoErrors();
});

test('registering with a taken address says signing in may bring a deleted account back', function () {
    $existing = User::factory()->create(['email' => 'sakib@example.com']);
    $existing->delete();

    $this->post(route('register.store'), [
        'name' => 'Sakib',
        'email' => 'sakib@example.com',
        'password' => 'password-123-Strong!',
        'password_confirmation' => 'password-123-Strong!',
        'role' => 'candidate',
    ])->assertSessionHasErrors([
        'email' => 'An account already uses this email. If it is yours — even one you deleted in the last '.User::DELETION_GRACE_DAYS.' days — sign in instead.',
    ]);
});
