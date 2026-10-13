<?php

namespace App\Http\Controllers;

use App\Concerns\ProfileValidationRules;
use App\Enums\SignInProvider;
use App\Models\ConnectedAccount;
use App\Models\User;
use App\Support\SubmissionLimits;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Fortify\Contracts\RegisterResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The one step between a new Google or GitHub account and an account
 * here: which side the person is joining for, and the name to show.
 *
 * The provider has already proved the email address, so nothing else is
 * asked, and no password: one can be added later from the security
 * settings. The account is only created here, once they press the
 * button, so leaving this page leaves nothing behind.
 */
class SocialSignUpController extends Controller
{
    use ProfileValidationRules;

    public const PENDING = 'social_sign_up';

    public const PENDING_MINUTES = 30;

    public function show(Request $request): View|RedirectResponse
    {
        $pending = $this->pending($request);

        if ($pending === null) {
            return redirect()->route('login');
        }

        return view('pages::auth.finish-sign-up', [
            'provider' => SignInProvider::from($pending['provider']),
            'email' => $pending['email'],
            'name' => $pending['name'],
            'role' => $pending['as'] === 'employer' ? 'employer' : 'candidate',
        ]);
    }

    public function store(Request $request): Response
    {
        $pending = $this->pending($request);

        if ($pending === null) {
            return redirect()->route('login')->withErrors([
                'social' => __('That took too long, so nothing was saved. Please start again.'),
            ]);
        }

        $limitKey = SubmissionLimits::registrationKey($request->ip());

        if (RateLimiter::tooManyAttempts($limitKey, SubmissionLimits::REGISTRATIONS_PER_HOUR)) {
            throw ValidationException::withMessages([
                'name' => SubmissionLimits::registrationRefusal($limitKey),
            ]);
        }

        $validated = $request->validate([
            'name' => $this->nameRules(),
            'role' => ['required', 'in:candidate,employer'],
        ]);

        $provider = SignInProvider::from($pending['provider']);

        // The address may have been taken since the provider sent it back
        // (another tab, the password form).
        if (User::withTrashed()->where('email', $pending['email'])->exists()) {
            $request->session()->forget(self::PENDING);

            return redirect()->route('login')->withErrors([
                'social' => SocialSignInController::emailTakenMessage($pending['email'], $provider),
            ]);
        }

        // Or the outside account was finished in another tab.
        if (ConnectedAccount::where('provider', $provider->value)->where('provider_user_id', $pending['provider_user_id'])->exists()) {
            $request->session()->forget(self::PENDING);

            return redirect()->route('login')->withErrors([
                'social' => __('That :provider account already has an account here. Sign in with it.', ['provider' => $provider->label()]),
            ]);
        }

        $user = DB::transaction(function () use ($pending, $validated, $provider) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $pending['email'],
                'password' => null,
            ]);

            // The provider vouched for the address before we got here
            // (SignInProvider::vouchesForEmail).
            $user->forceFill(['email_verified_at' => now()])->save();

            $user->connectedAccounts()->create([
                'provider' => $provider,
                'provider_user_id' => $pending['provider_user_id'],
                'email' => $pending['email'],
            ]);

            if ($validated['role'] === 'candidate') {
                $user->candidateProfile()->create([]);
            }

            return $user;
        });

        RateLimiter::hit($limitKey, 3600);
        $request->session()->forget(self::PENDING);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        // The same way out as the sign-up form: an invitation being
        // followed first, then the side they chose.
        return app(RegisterResponse::class)->toResponse($request);
    }

    /**
     * @return array{provider: string, provider_user_id: string, email: string, name: ?string, as: ?string, until: int}|null
     */
    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::PENDING);

        if (! is_array($pending) || ($pending['until'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        return $pending;
    }
}
