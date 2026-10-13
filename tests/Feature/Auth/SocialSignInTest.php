<?php

use App\Enums\AccountStatus;
use App\Enums\SignInProvider;
use App\Http\Controllers\SocialSignInController;
use App\Http\Controllers\SocialSignUpController;
use App\Models\ConnectedAccount;
use App\Models\User;
use App\Support\SubmissionLimits;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

/*
 * Signing in and up with Google or GitHub. The provider's side of the
 * round trip is faked; what is tested is what we do with its answer.
 */

beforeEach(function () {
    configureSignInProviders();
});

function pendingSignUp(array $overrides = []): array
{
    return [SocialSignUpController::PENDING => [
        'provider' => 'google',
        'provider_user_id' => 'google-123',
        'email' => 'nadia@gmail.com',
        'name' => 'Nadia Rahman',
        'as' => null,
        'until' => now()->addMinutes(SocialSignUpController::PENDING_MINUTES)->getTimestamp(),
        ...$overrides,
    ]];
}

test('the sign-in and sign-up pages offer the providers that are set up, before the email fields', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSeeInOrder(['Continue with Google', 'Continue with GitHub', 'or', 'Email address']);

    // On sign-up, after "I want to", inside the form so the side goes along.
    $this->get(route('register'))
        ->assertOk()
        ->assertSeeInOrder(['I want to', 'Continue with Google', 'Continue with GitHub', 'Full name'])
        ->assertSee('formaction="'.route('social.start', 'google').'"', false);

    config(['services.github.client_secret' => null]);

    $this->get(route('login'))->assertSee('Continue with Google')->assertDontSee('Continue with GitHub');
});

test('with no provider set up there are no buttons and no divider', function () {
    config(['services.google.client_id' => null, 'services.github.client_id' => null]);

    $this->get(route('login'))->assertOk()->assertDontSee('Continue with');
    $this->get(route('register'))->assertOk()->assertDontSee('Continue with');
});

test('continuing with a provider leaves for it, remembering why and, from sign-up, which side', function () {
    Socialite::fake('google');

    $this->get(route('social.redirect', 'google'))->assertRedirect();

    expect(session(SocialSignInController::INTENT))->toBe(['purpose' => 'sign-in', 'as' => null, 'provider' => 'google']);

    // Only the side is read from the sign-up form, whatever else was typed.
    $this->post(route('social.start', 'google'), ['role' => 'employer', 'name' => 'Nadia', 'password' => 'typed-already'])
        ->assertRedirect();

    expect(session(SocialSignInController::INTENT))->toBe(['purpose' => 'sign-in', 'as' => 'employer', 'provider' => 'google']);
});

test('a provider that is not set up, or not known, is not found', function () {
    config(['services.google.client_id' => null]);

    $this->get('/auth/google/redirect')->assertNotFound();
    $this->get('/auth/google/callback')->assertNotFound();
    $this->get('/auth/twitter/redirect')->assertNotFound();
});

test('a new person finishes signing up as a candidate, with no password and a verified address', function () {
    fakeOutsideAccount('google');

    $this->get(route('social.callback', 'google'))->assertRedirect(route('social.finish'));

    $this->assertGuest();
    expect(User::count())->toBe(0);

    $this->get(route('social.finish'))
        ->assertOk()
        ->assertSee('Signing up with Google as nadia@gmail.com.')
        ->assertViewHas('name', 'Nadia Rahman')
        ->assertViewHas('role', 'candidate')
        ->assertSee(route('terms'), false);

    $this->post(route('social.finish.store'), ['name' => 'Nadia Rahman', 'role' => 'candidate'])
        ->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'nadia@gmail.com')->sole();

    $this->assertAuthenticatedAs($user);
    expect($user->password)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->candidateProfile)->not->toBeNull()
        ->and($user->connectedAccounts()->sole()->only(['provider_user_id', 'email']))
        ->toBe(['provider_user_id' => 'google-123', 'email' => 'nadia@gmail.com'])
        ->and(session(SocialSignUpController::PENDING))->toBeNull();
});

test('choosing Hire before continuing starts the last step on that side and ends at company setup', function () {
    fakeOutsideAccount('github');

    $this->post(route('social.start', 'github'), ['role' => 'employer']);
    $this->get(route('social.callback', 'github'))->assertRedirect(route('social.finish'));
    $this->get(route('social.finish'))->assertViewHas('role', 'employer');

    $this->post(route('social.finish.store'), ['name' => 'Nadia Rahman', 'role' => 'employer'])
        ->assertRedirect(route('companies.create'));

    expect(User::sole()->candidateProfile)->toBeNull();
});

test('a GitHub account without a name offers its username, and the name can be changed', function () {
    fakeOutsideAccount('github', ['name' => null, 'nickname' => 'tomh']);

    $this->get(route('social.callback', 'github'));
    $this->get(route('social.finish'))->assertViewHas('name', 'tomh');

    $this->post(route('social.finish.store'), ['name' => 'Tom Hughes', 'role' => 'candidate']);

    expect(User::sole()->name)->toBe('Tom Hughes');
});

test('the last step needs a name and a side', function () {
    $this->withSession(pendingSignUp())
        ->post(route('social.finish.store'), ['name' => '', 'role' => 'admin'])
        ->assertSessionHasErrors(['name', 'role']);

    expect(User::count())->toBe(0);
});

test('leaving the last step creates nothing, and it runs out', function () {
    $this->withSession(pendingSignUp(['until' => now()->subMinute()->getTimestamp()]));

    $this->get(route('social.finish'))->assertRedirect(route('login'));
    $this->post(route('social.finish.store'), ['name' => 'Nadia Rahman', 'role' => 'candidate'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('social');

    expect(User::count())->toBe(0);
});

test('no account is made from an address the provider has not verified', function (string $provider, array $answer) {
    fakeOutsideAccount($provider, $answer);

    $this->get(route('social.callback', $provider))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('social');

    $this->assertGuest();
    expect(session(SocialSignUpController::PENDING))->toBeNull();
})->with([
    'Google, unverified' => ['google', ['email_verified' => false]],
    'GitHub, no verified primary address' => ['github', ['email' => null]],
]);

test('an address that already has an account is not joined to it', function (callable $existing, string $returned) {
    $existing();
    fakeOutsideAccount('google', ['email' => $returned]);

    $this->get(route('social.callback', 'google'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['social' => SocialSignInController::emailTakenMessage('nadia@gmail.com', SignInProvider::Google)]);

    $this->assertGuest();
    expect(ConnectedAccount::count())->toBe(0);
})->with([
    'an account with a password' => [fn () => User::factory()->create(['email' => 'nadia@gmail.com']), 'nadia@gmail.com'],
    'the same address in other letters' => [fn () => User::factory()->create(['email' => 'nadia@gmail.com']), 'Nadia@Gmail.com'],
    'an account deleted in the last 30 days' => [fn () => User::factory()->create(['email' => 'nadia@gmail.com'])->delete(), 'nadia@gmail.com'],
]);

test('the address taken while on the last step stops it', function () {
    User::factory()->create(['email' => 'nadia@gmail.com']);

    $this->withSession(pendingSignUp())
        ->post(route('social.finish.store'), ['name' => 'Nadia Rahman', 'role' => 'candidate'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('social');

    expect(User::count())->toBe(1)->and(ConnectedAccount::count())->toBe(0);
});

test('a connected account signs in, even after its address changed at the provider', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);
    $user->connectedAccounts()->create(['provider' => 'google', 'provider_user_id' => 'google-123', 'email' => 'old@example.com']);
    fakeOutsideAccount('google', ['email' => 'new@gmail.com']);

    $this->get(route('social.callback', 'google'))->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('two-factor is still asked for', function () {
    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code-1'])),
        'two_factor_confirmed_at' => now(),
    ])->save();
    $user->connectedAccounts()->create(['provider' => 'github', 'provider_user_id' => 'github-123']);
    fakeOutsideAccount('github');

    $this->get(route('social.callback', 'github'))->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
    expect(session('login.id'))->toBe($user->id);
});

test('a suspended account is refused', function () {
    $user = User::factory()->create(['account_status' => AccountStatus::Suspended]);
    $user->connectedAccounts()->create(['provider' => 'google', 'provider_user_id' => 'google-123']);
    fakeOutsideAccount('google');

    $this->get(route('social.callback', 'google'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('social');

    $this->assertGuest();
});

test('a deleted account inside its grace period is offered back, not signed in', function () {
    $user = User::factory()->create();
    $user->connectedAccounts()->create(['provider' => 'google', 'provider_user_id' => 'google-123']);
    $user->delete();
    fakeOutsideAccount('google');

    $this->get(route('social.callback', 'google'))->assertRedirect(route('account.restore'));

    $this->assertGuest();
    $this->get(route('account.restore'))->assertOk()->assertSee($user->email);
});

test('cancelling at the provider goes back to the sign-in page without an error', function () {
    $this->get(route('social.callback', ['provider' => 'google', 'error' => 'access_denied']))
        ->assertRedirect(route('login'))
        ->assertSessionHasNoErrors();
});

test('a round trip that fails says so', function () {
    Socialite::fake('google', fn () => throw new InvalidStateException);

    $this->get(route('social.callback', 'google'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('social');

    $this->assertGuest();
});

test('someone already signed in is sent to their dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('social.callback', 'google'))
        ->assertRedirect(route('dashboard'));
});

test('signing up this way counts against the same limit as the form', function () {
    $key = SubmissionLimits::registrationKey('127.0.0.1');

    for ($i = 0; $i < SubmissionLimits::REGISTRATIONS_PER_HOUR; $i++) {
        RateLimiter::hit($key, 3600);
    }

    $this->withSession(pendingSignUp())
        ->post(route('social.finish.store'), ['name' => 'Nadia Rahman', 'role' => 'candidate'])
        ->assertSessionHasErrors('name');

    expect(User::count())->toBe(0);
});

test('an invitation being followed is where the new account lands', function () {
    $this->withSession([...pendingSignUp(), 'url.intended' => url('/invitations/some-token')])
        ->post(route('social.finish.store'), ['name' => 'Nadia Rahman', 'role' => 'employer'])
        ->assertRedirect('/invitations/some-token');
});
