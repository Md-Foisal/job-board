<?php

namespace App\Http\Controllers;

use App\Enums\AccountStatus;
use App\Enums\SignInProvider;
use App\Filament\Support\ConfirmsPassword;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\ConnectedAccount;
use App\Models\User;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Features;
use Laravel\Socialite\Contracts\User as ProviderUser;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signing in with Google or GitHub, for three purposes that share one
 * callback URL per provider: signing in (or up), connecting the account
 * from the security settings, and confirming it is you before a
 * sensitive change when there is no password to type.
 *
 * Which of the three a callback answers is decided when the person sets
 * off, and kept in the session for the round trip.
 *
 * An outside account is matched by the provider's id only, never by
 * email (Google: an address can belong to different accounts over time).
 * When a new one carries the email address of an existing account, it is
 * not joined to that account automatically: the owner signs in the usual
 * way and connects it from their settings, so both sides are proved
 * first. Joining on the email alone would hand the account to whoever
 * controls that address at the provider -- and, while addresses are not
 * verified here, let someone who registered an address they do not own
 * keep a way into the real owner's account: account "pre-hijacking",
 * which Sudhodanan and Paverd found at 35 of 75 popular services (2022).
 */
class SocialSignInController extends Controller
{
    public const INTENT = 'social_sign_in';

    /**
     * From the sign-in page, where nobody has said which side they are
     * joining for; a newcomer is asked on the last step.
     */
    public function redirect(Request $request, SignInProvider $provider): Response
    {
        return $this->setOff($request, $provider, ['purpose' => 'sign-in', 'as' => null]);
    }

    /**
     * From the sign-up form, whose "I want to" comes first: the side chosen
     * there is carried through, and only that field is read.
     */
    public function start(Request $request, SignInProvider $provider): Response
    {
        return $this->setOff($request, $provider, [
            'purpose' => 'sign-in',
            'as' => $request->input('role') === 'employer' ? 'employer' : null,
        ]);
    }

    /**
     * Adding a way into an account is the change the confirmation window
     * exists for, so it needs one, as the security page does.
     */
    public function connect(Request $request, SignInProvider $provider): Response
    {
        $back = url()->previous(route('security.edit'));

        if (! ConfirmsPassword::recentlyConfirmed()) {
            redirect()->setIntendedUrl($back);

            return redirect()->route('password.confirm');
        }

        return $this->setOff($request, $provider, ['purpose' => 'connect', 'back' => $back]);
    }

    /**
     * For someone without a password, signing in with a connected account
     * again is how they confirm it is them.
     */
    public function confirm(Request $request, SignInProvider $provider): Response
    {
        if (! $request->session()->has('url.intended')) {
            redirect()->setIntendedUrl(url()->previous(route('dashboard')));
        }

        return $this->setOff($request, $provider, ['purpose' => 'confirm']);
    }

    public function callback(Request $request, SignInProvider $provider): Response
    {
        abort_unless($provider->isConfigured(), 404);

        $intent = $request->session()->pull(self::INTENT);
        $purpose = is_array($intent) && ($intent['provider'] ?? null) === $provider->value
            ? $intent['purpose']
            : 'sign-in';

        // Connecting and confirming belong to a signed-in person; if the
        // session ended on the way, the trip can only be a sign-in.
        if ($purpose !== 'sign-in' && $request->user() === null) {
            $purpose = 'sign-in';
        }

        if ($purpose === 'sign-in' && $request->user() !== null) {
            return redirect()->route('dashboard');
        }

        // The person chose "Cancel" at the provider: nothing went wrong, so
        // they are simply taken back to where they started.
        if ($request->filled('error')) {
            return $this->backTo($purpose, $intent);
        }

        try {
            $outside = Socialite::driver($provider->value)->user();
        } catch (InvalidStateException|GuzzleException $exception) {
            Log::warning('Signing in with '.$provider->label().' failed.', ['exception' => $exception::class]);

            return $this->backTo($purpose, $intent, __('We could not sign you in with :provider. Please try again.', [
                'provider' => $provider->label(),
            ]));
        }

        return match ($purpose) {
            'connect' => $this->connectTo($request->user(), $provider, $outside, $intent['back'] ?? route('security.edit')),
            'confirm' => $this->confirmAs($request, $provider, $outside),
            default => $this->signIn($request, $provider, $outside, $intent['as'] ?? null),
        };
    }

    /**
     * @param  array<string, string|null>  $intent
     */
    private function setOff(Request $request, SignInProvider $provider, array $intent): Response
    {
        abort_unless($provider->isConfigured(), 404);

        $request->session()->put(self::INTENT, [...$intent, 'provider' => $provider->value]);

        return Socialite::driver($provider->value)->redirect();
    }

    private function signIn(Request $request, SignInProvider $provider, ProviderUser $outside, ?string $as): Response
    {
        $connected = ConnectedAccount::query()
            ->where('provider', $provider->value)
            ->where('provider_user_id', (string) $outside->getId())
            ->first();

        if ($connected !== null) {
            return $this->signInAs($request, User::withTrashed()->findOrFail($connected->user_id));
        }

        if (! Features::enabled(Features::registration())) {
            return $this->backTo('sign-in', null, __('No account here is connected to that :provider account.', ['provider' => $provider->label()]));
        }

        if (! $provider->vouchesForEmail($outside)) {
            return $this->backTo('sign-in', null, __(':provider did not share a verified email address with us, so an account cannot be made from it. Verify your email address with :provider, or create an account with your email instead.', [
                'provider' => $provider->label(),
            ]));
        }

        $email = Str::lower($outside->getEmail());

        if (User::withTrashed()->where('email', $email)->exists()) {
            return $this->backTo('sign-in', null, self::emailTakenMessage($email, $provider));
        }

        $request->session()->put(SocialSignUpController::PENDING, [
            'provider' => $provider->value,
            'provider_user_id' => (string) $outside->getId(),
            'email' => $email,
            'name' => $outside->getName() ?: $outside->getNickname(),
            'as' => $as,
            'until' => now()->addMinutes(SocialSignUpController::PENDING_MINUTES)->getTimestamp(),
        ]);

        return redirect()->route('social.finish');
    }

    /**
     * The same checks the password sign-in makes (FortifyServiceProvider),
     * in the same order: a suspension refuses, a deleted account inside
     * its grace period is offered back, and two-factor is still asked for.
     */
    private function signInAs(Request $request, User $user): Response
    {
        if ($user->anonymized_at !== null || ($user->trashed() && ! $user->isRestorable())) {
            return $this->backTo('sign-in', null, __('No account here is connected to that account any more.'));
        }

        if ($user->account_status !== AccountStatus::Active) {
            return $this->backTo('sign-in', null, __(EnsureAccountIsActive::MESSAGE));
        }

        if ($user->trashed()) {
            $request->session()->put(AccountRestoreController::SESSION_KEY, [
                'user_id' => $user->id,
                'until' => now()->addMinutes(10)->getTimestamp(),
            ]);

            return redirect()->route('account.restore');
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => false,
            ]);

            TwoFactorAuthenticationChallenged::dispatch($user);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return app(LoginResponse::class)->toResponse($request);
    }

    private function connectTo(User $user, SignInProvider $provider, ProviderUser $outside, string $back): RedirectResponse
    {
        $connected = ConnectedAccount::query()
            ->where('provider', $provider->value)
            ->where('provider_user_id', (string) $outside->getId())
            ->first();

        if ($connected !== null && $connected->user_id !== $user->id) {
            return redirect()->to($back)->with('error', __('That :provider account is already connected to another account here.', [
                'provider' => $provider->label(),
            ]));
        }

        if ($connected === null) {
            if ($user->connectedAccounts()->where('provider', $provider->value)->exists()) {
                return redirect()->to($back)->with('error', __('A different :provider account is already connected. Disconnect it first.', [
                    'provider' => $provider->label(),
                ]));
            }

            $user->connectedAccounts()->create([
                'provider' => $provider,
                'provider_user_id' => (string) $outside->getId(),
                'email' => $outside->getEmail(),
            ]);
        }

        return redirect()->to($back)->with('success', __(':provider connected. You can sign in with it now.', [
            'provider' => $provider->label(),
        ]));
    }

    private function confirmAs(Request $request, SignInProvider $provider, ProviderUser $outside): RedirectResponse
    {
        $owns = $request->user()->connectedAccounts()
            ->where('provider', $provider->value)
            ->where('provider_user_id', (string) $outside->getId())
            ->exists();

        if (! $owns) {
            return $this->backTo('confirm', null, __('That is not the :provider account connected to you. Choose the one you sign in with.', [
                'provider' => $provider->label(),
            ]));
        }

        $request->session()->passwordConfirmed();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * @param  array<string, string|null>|null  $intent
     */
    private function backTo(string $purpose, ?array $intent, ?string $error = null): RedirectResponse
    {
        if ($purpose === 'connect') {
            $redirect = redirect()->to($intent['back'] ?? route('security.edit'));

            return $error === null ? $redirect : $redirect->with('error', $error);
        }

        $redirect = redirect()->route($purpose === 'confirm' ? 'password.confirm' : 'login');

        return $error === null ? $redirect : $redirect->withErrors(['social' => $error]);
    }

    /**
     * Said for every taken address, a deleted account's included, so it
     * reveals no more than the sign-up form already does.
     */
    public static function emailTakenMessage(string $email, SignInProvider $provider): string
    {
        return __('An account already uses :email. If it is yours — even one you deleted in the last :days days — sign in the way you usually do, then connect :provider in your security settings.', [
            'email' => $email,
            'days' => User::DELETION_GRACE_DAYS,
            'provider' => $provider->label(),
        ]);
    }
}
