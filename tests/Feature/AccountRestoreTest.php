<?php

use App\Actions\AnonymizeUser;
use App\Actions\SuspendUser;
use App\Enums\StaffRole;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\User;

function deletedAccount(): User
{
    $user = candidateUser();
    $user->forceFill(['email' => 'sakib@example.com', 'password' => 'password'])->save();
    $user->delete();

    return $user;
}

test('signing in to a deleted account offers to restore it, without signing in', function () {
    deletedAccount();

    $this->post(route('login.store'), ['email' => 'sakib@example.com', 'password' => 'password'])
        ->assertRedirect(route('account.restore'));
    $this->assertGuest();

    $this->get(route('account.restore'))
        ->assertOk()
        ->assertSee('sakib@example.com')
        ->assertSee(now()->addDays(User::DELETION_GRACE_DAYS)->toFormattedDateString());
});

test('restoring brings the account back and sends them to sign in as usual', function () {
    $user = deletedAccount();

    $this->post(route('login.store'), ['email' => 'sakib@example.com', 'password' => 'password']);

    $this->post(route('account.restore.store'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');

    expect($user->fresh()->trashed())->toBeFalse();
    $this->assertGuest();

    $this->post(route('login.store'), ['email' => 'sakib@example.com', 'password' => 'password']);
    $this->assertAuthenticatedAs($user->fresh());
});

test('the wrong password reveals nothing about a deleted account', function () {
    deletedAccount();

    $this->post(route('login.store'), ['email' => 'sakib@example.com', 'password' => 'wrong'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->get(route('account.restore'))->assertRedirect(route('login'));
});

test('the restore page cannot be reached or used without the sign-in first', function () {
    $user = deletedAccount();

    $this->get(route('account.restore'))->assertRedirect(route('login'));
    $this->post(route('account.restore.store'))->assertRedirect(route('login'));

    expect($user->fresh()->trashed())->toBeTrue();
});

test('the offer lapses after ten minutes', function () {
    $user = deletedAccount();
    $this->post(route('login.store'), ['email' => 'sakib@example.com', 'password' => 'password']);

    $this->travel(11)->minutes();

    $this->post(route('account.restore.store'))->assertRedirect(route('login'));
    expect($user->fresh()->trashed())->toBeTrue();
});

test('past the grace period, or once erased, there is nothing to restore', function () {
    $late = deletedAccount();
    $this->travel(User::DELETION_GRACE_DAYS + 1)->days();

    $this->post(route('login.store'), ['email' => 'sakib@example.com', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    app(AnonymizeUser::class)(User::withTrashed()->find($late->id));

    $this->post(route('login.store'), ['email' => 'sakib@example.com', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);
});

test('restoring does not lift a suspension', function () {
    $user = deletedAccount();
    app(SuspendUser::class)(User::withTrashed()->find($user->id), staffWithTwoFactor(StaffRole::SuperAdmin), 'Scam postings.');

    $this->post(route('login.store'), ['email' => 'sakib@example.com', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => EnsureAccountIsActive::MESSAGE]);

    $this->get(route('account.restore'))->assertRedirect(route('login'));
});
