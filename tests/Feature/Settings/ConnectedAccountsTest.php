<?php

use App\Actions\AnonymizeUser;
use App\Http\Controllers\SocialSignInController;
use App\Models\ConnectedAccount;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Socialite;
use Livewire\Livewire;

/*
 * Living with a Google or GitHub account: connecting and disconnecting it
 * from the security settings, and, for someone who never set a password,
 * adding one and confirming it is them before a sensitive change.
 */

beforeEach(function () {
    configureSignInProviders();
});

function passwordlessUser(string $provider = 'google'): User
{
    $user = User::factory()->create(['password' => null]);
    $user->connectedAccounts()->create(['provider' => $provider, 'provider_user_id' => $provider.'-123', 'email' => 'nadia@gmail.com']);

    return $user;
}

test('connecting needs a fresh confirmation first', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->post(route('social.connect', 'google'))
        ->assertRedirect(route('password.confirm'));

    expect(session('url.intended'))->toBe(route('security.edit'));
});

test('a confirmed person leaves for the provider to connect', function () {
    Socialite::fake('google');

    $this->actingAs(User::factory()->create())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->from(route('security.edit'))
        ->post(route('social.connect', 'google'))
        ->assertRedirect();

    expect(session(SocialSignInController::INTENT))->toMatchArray(['purpose' => 'connect', 'provider' => 'google']);
});

test('the round trip connects the account to the person signed in', function () {
    $user = User::factory()->create();
    fakeOutsideAccount('github', ['email' => 'nadia@users.noreply.github.com']);

    $this->actingAs($user)
        ->withSession([SocialSignInController::INTENT => ['purpose' => 'connect', 'back' => route('security.edit'), 'provider' => 'github']])
        ->get(route('social.callback', 'github'))
        ->assertRedirect(route('security.edit'))
        ->assertSessionHas('success');

    expect($user->connectedAccounts()->sole()->only(['provider_user_id', 'email']))
        ->toBe(['provider_user_id' => 'github-123', 'email' => 'nadia@users.noreply.github.com']);
});

test('an outside account connected to someone else is not moved', function () {
    $owner = passwordlessUser('github');
    $user = User::factory()->create();
    fakeOutsideAccount('github');

    $this->actingAs($user)
        ->withSession([SocialSignInController::INTENT => ['purpose' => 'connect', 'back' => route('security.edit'), 'provider' => 'github']])
        ->get(route('social.callback', 'github'))
        ->assertRedirect(route('security.edit'))
        ->assertSessionHas('error');

    expect(ConnectedAccount::sole()->user_id)->toBe($owner->id);
});

test('the security page lists each provider with what can be done', function () {
    $user = passwordlessUser('google');

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertSee('Connected accounts')
        ->assertSee('nadia@gmail.com')
        ->assertSee('Your only way to sign in. Add a password to disconnect it.')
        ->assertSee(route('social.connect', 'github'), false);
});

test('the only way in cannot be disconnected; one of two can', function () {
    $user = passwordlessUser('google');
    $this->actingAs($user);

    Livewire::test('pages::settings.security')->call('disconnect', 'google');
    expect($user->connectedAccounts()->count())->toBe(1);

    $user->connectedAccounts()->create(['provider' => 'github', 'provider_user_id' => 'github-123']);

    Livewire::test('pages::settings.security')->call('disconnect', 'google');
    expect($user->connectedAccounts()->pluck('provider_user_id')->all())->toBe(['github-123']);
});

test('with a password, the last connected account can go', function () {
    $user = User::factory()->create();
    $user->connectedAccounts()->create(['provider' => 'google', 'provider_user_id' => 'google-123']);

    $this->actingAs($user);
    Livewire::test('pages::settings.security')->call('disconnect', 'google');

    expect($user->connectedAccounts()->count())->toBe(0);
});

test('without a password, the security page adds one without asking for a current one', function () {
    $user = passwordlessUser();
    $this->actingAs($user);

    Livewire::test('pages::settings.security')
        ->assertSee('Add password')
        ->assertDontSee('Current password')
        ->set('password', 'new-password')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('without a password, confirming it is you is signing in again', function () {
    $user = passwordlessUser('google');

    $this->actingAs($user)
        ->get(route('password.confirm'))
        ->assertOk()
        ->assertSee('Continue with Google')
        ->assertSee(route('social.confirm', 'google'), false)
        ->assertDontSee('name="password"', false);
});

test('signing in again with the connected account confirms it is you', function () {
    $user = passwordlessUser('google');
    fakeOutsideAccount('google');

    $this->actingAs($user)
        ->withSession([
            SocialSignInController::INTENT => ['purpose' => 'confirm', 'provider' => 'google'],
            'url.intended' => route('security.edit'),
        ])
        ->get(route('social.callback', 'google'))
        ->assertRedirect(route('security.edit'));

    expect(session('auth.password_confirmed_at'))->not->toBeNull();
});

test('a different outside account does not confirm it is you', function () {
    $user = passwordlessUser('google');
    fakeOutsideAccount('google', ['id' => 'someone-else']);

    $this->actingAs($user)
        ->withSession([SocialSignInController::INTENT => ['purpose' => 'confirm', 'provider' => 'google']])
        ->get(route('social.callback', 'google'))
        ->assertRedirect(route('password.confirm'))
        ->assertSessionHasErrors('social');

    expect(session('auth.password_confirmed_at'))->toBeNull();
});

test('without a password, deleting the account waits for a fresh sign-in', function () {
    $user = passwordlessUser('google');
    $this->actingAs($user);

    Livewire::test('pages::settings.delete-user-modal')
        ->assertSee('Continue with Google')
        ->call('deleteUser')
        ->assertHasErrors('password');

    expect($user->fresh()->trashed())->toBeFalse();

    session(['auth.password_confirmed_at' => time()]);

    Livewire::test('pages::settings.delete-user-modal')
        ->assertDontSee('Continue with Google')
        ->call('deleteUser')
        ->assertHasNoErrors();

    expect(User::withTrashed()->find($user->id)->trashed())->toBeTrue();
});

test('erasing a person removes the outside accounts they signed in with', function () {
    $user = passwordlessUser('google');

    app(AnonymizeUser::class)($user);

    expect(ConnectedAccount::count())->toBe(0);
});
