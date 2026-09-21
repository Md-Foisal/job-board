<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bringing back an account its owner deleted, inside the grace period.
 *
 * Reached only from the sign-in form, after the right password for a
 * deleted account: the session then holds which account, for ten
 * minutes. Restoring does not sign anyone in -- the person signs in again
 * as usual, so a second factor, if they have one, is still asked for.
 * Restoring also lifts nothing else: a suspension stays a suspension.
 */
class AccountRestoreController extends Controller
{
    public const SESSION_KEY = 'account_restore';

    public function show(Request $request): View|RedirectResponse
    {
        $user = $this->pending($request);

        if ($user === null) {
            return redirect()->route('login');
        }

        return view('auth.restore-account', [
            'email' => $user->email,
            'erasesAt' => $user->erasesAt(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->pending($request);
        $request->session()->forget(self::SESSION_KEY);

        if ($user === null) {
            return redirect()->route('login');
        }

        $user->restore();

        return redirect()->route('login')->with('status', __('Your account is restored. Sign in to carry on.'));
    }

    private function pending(Request $request): ?User
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        if (! is_array($pending) || ($pending['until'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        $user = User::withTrashed()->find($pending['user_id'] ?? null);

        return $user?->isRestorable() ? $user : null;
    }
}
