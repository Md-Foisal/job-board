<?php

namespace App\Filament\Support;

use Filament\Forms\Components\TextInput;

/**
 * Re-asks for the staff member's password before an action that takes
 * something away from someone -- banning a company, suspending a user.
 *
 * It shares Laravel's own confirmation window (auth.password_timeout,
 * stored as auth.password_confirmed_at in the session), so a password
 * entered on the security settings page or on one admin action covers
 * the next ones too, instead of asking every single time. That is the
 * same "sudo mode" pattern GitHub uses.
 *
 * Kept as two small pieces rather than a wrapper around the action, so an
 * action that has its own fields (a reason, say) can add this alongside
 * them:
 *
 *     ->schema(fn () => [Textarea::make('reason'), ...ConfirmsPassword::fields()])
 *     ->before(fn () => ConfirmsPassword::remember())
 */
final class ConfirmsPassword
{
    public static function recentlyConfirmed(): bool
    {
        $confirmedAt = (int) session('auth.password_confirmed_at', 0);

        return (now()->getTimestamp() - $confirmedAt) < (int) config('auth.password_timeout', 10800);
    }

    /**
     * @return array<int, TextInput>
     */
    public static function fields(): array
    {
        if (self::recentlyConfirmed()) {
            return [];
        }

        return [
            TextInput::make('current_password')
                ->label(__('Your password'))
                ->helperText(__('This action cannot be taken without confirming it is you.'))
                ->password()
                ->revealable()
                ->currentPassword()
                ->required(),
        ];
    }

    /**
     * Starts (or restarts) the confirmation window once the password has
     * passed validation.
     */
    public static function remember(): void
    {
        session()->passwordConfirmed();
    }
}
