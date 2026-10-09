<?php

use App\Support\PasswordPolicy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

function passwordPasses(string $password): bool
{
    return Validator::make(['password' => $password], ['password' => Password::default()])->passes();
}

test('in production a password needs length, not a mix of character types', function () {
    app()->detectEnvironment(fn () => 'production');

    // The breach check asks the Pwned Passwords range API; an empty
    // answer means the password is in no known breach.
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);

    expect(PasswordPolicy::minLength())->toBe(15)
        ->and(passwordPasses('correct horse battery'))->toBeTrue()
        ->and(passwordPasses('Sh0rt!Pass#1'))->toBeFalse();
});

test('in production a password found in a known breach is refused', function () {
    app()->detectEnvironment(fn () => 'production');

    $password = 'correct horse battery';
    $hash = strtoupper(sha1($password));
    Http::fake(['api.pwnedpasswords.com/*' => Http::response(substr($hash, 5).':12345')]);

    expect(passwordPasses($password))->toBeFalse();
});

test('outside production the floor is eight characters, for demo accounts and tests', function () {
    expect(PasswordPolicy::minLength())->toBe(8)
        ->and(passwordPasses('password'))->toBeTrue()
        ->and(passwordPasses('pass'))->toBeFalse();
});

test('the hint states the same length the rule checks', function () {
    expect(PasswordPolicy::hint())->toContain((string) PasswordPolicy::minLength());
});
