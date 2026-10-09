<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * What a new password has to be, in one place, so the rule that checks it
 * and the hint that describes it on the form cannot disagree.
 *
 * Length and a check against known breached passwords, and nothing else:
 * NIST SP 800-63B-4 asks for at least 15 characters when the password is
 * the only factor, and forbids rules about mixing character types, which
 * push people towards "Password1!" rather than towards anything strong.
 * Pasting is allowed and the field can be shown, so a password manager or
 * a long phrase is the easy path. Outside production the floor is
 * Laravel's own eight, so demo accounts and tests can use short ones.
 */
class PasswordPolicy
{
    public static function minLength(): int
    {
        return app()->isProduction() ? 15 : 8;
    }

    public static function rule(): Password
    {
        $rule = Password::min(self::minLength());

        return app()->isProduction() ? $rule->uncompromised() : $rule;
    }

    /**
     * The sentence shown under a new-password field before anything is
     * typed, rather than in an error after the first attempt.
     */
    public static function hint(): string
    {
        return __('At least :count characters. A few words in a row are easy to remember and hard to guess.', [
            'count' => self::minLength(),
        ]);
    }
}
