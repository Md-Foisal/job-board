<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Enums\AccountStatus;
use App\Http\Controllers\AccountRestoreController;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->bindRegisterResponse();

        $this->app->bind(DisableTwoFactorAuthentication::class, \App\Actions\Fortify\DisableTwoFactorAuthentication::class);
        $this->app->bind(EnableTwoFactorAuthentication::class, \App\Actions\Fortify\EnableTwoFactorAuthentication::class);
    }

    /**
     * The register form's "what brings you here" choice is not stored on
     * the user -- candidate and employer are both derived from
     * relationships rather than from a column. What it does decide is
     * which relationship gets created first, and registering to hire
     * creates nothing at all, because a company needs a name the signup
     * form never asked for.
     *
     * So the choice has to be acted on at the only moment it is known:
     * the redirect out of registration. Afterwards there is no way to
     * tell a new employer apart from any other account with nothing set
     * up yet, which is exactly why /dashboard cannot make this decision.
     */
    private function bindRegisterResponse(): void
    {
        $this->app->singleton(RegisterResponse::class, fn () => new class implements RegisterResponse
        {
            public function toResponse($request)
            {
                // An invitation has already decided where this person is
                // going: they were asked to join a company that exists,
                // not to start one. That stashed destination outranks the
                // "what brings you here" radio -- otherwise the most
                // common invitation case, someone with no account at all,
                // follows the link, registers, and is handed a "name your
                // company" form instead of the invitation they came for.
                if ($request->session()->has('url.intended')) {
                    return redirect()->intended(config('fortify.home'));
                }

                return $request->input('role') === 'employer'
                    ? redirect()->route('companies.create')
                    : redirect()->intended(config('fortify.home'));
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);

        // Fortify's own credential check, plus one refusal: a suspended
        // account. The suspension is only revealed after the password has
        // been checked, so the message tells nobody anything about an
        // account they could not have signed in to anyway.
        //
        // An account its owner deleted is found too, while it can still be
        // restored: signing in is how they get it back (the same way
        // Facebook cancels a pending deletion). They are not signed in
        // here -- only sent to confirm -- and after restoring they sign in
        // again through the normal path, so two-factor still applies.
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::withTrashed()
                ->where(Fortify::username(), $request->input(Fortify::username()))
                ->whereNull('anonymized_at')
                ->first();

            if ($user === null || ! Hash::check($request->input('password'), $user->password)) {
                return null;
            }

            if ($user->account_status !== AccountStatus::Active) {
                throw ValidationException::withMessages([
                    Fortify::username() => EnsureAccountIsActive::MESSAGE,
                ]);
            }

            if ($user->trashed()) {
                if (! $user->isRestorable()) {
                    return null;
                }

                $request->session()->put(AccountRestoreController::SESSION_KEY, [
                    'user_id' => $user->id,
                    'until' => now()->addMinutes(10)->getTimestamp(),
                ]);

                throw new HttpResponseException(redirect()->route('account.restore'));
            }

            return $user;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn () => view('pages::auth.login'));
        Fortify::verifyEmailView(fn () => view('pages::auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('pages::auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('pages::auth.confirm-password'));
        Fortify::registerView(fn () => view('pages::auth.register'));
        Fortify::resetPasswordView(fn () => view('pages::auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('pages::auth.forgot-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
