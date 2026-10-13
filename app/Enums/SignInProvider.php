<?php

namespace App\Enums;

use Laravel\Socialite\Contracts\User as ProviderUser;

/**
 * The outside accounts people can sign in with. A provider is offered only
 * once its credentials are set (config/services.php), so a copy of the
 * site without them simply shows no buttons.
 */
enum SignInProvider: string
{
    case Google = 'google';
    case GitHub = 'github';

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::GitHub => 'GitHub',
        };
    }

    public function isConfigured(): bool
    {
        return filled(config("services.{$this->value}.client_id"))
            && filled(config("services.{$this->value}.client_secret"));
    }

    /**
     * @return list<self>
     */
    public static function configured(): array
    {
        return array_values(array_filter(self::cases(), fn (self $provider) => $provider->isConfigured()));
    }

    /**
     * Whether the provider vouches that the person owns the email address
     * it handed over. An account is only created on an address someone
     * has proved is theirs; otherwise its real owner could later reset
     * the password and share an account with a stranger.
     */
    public function vouchesForEmail(ProviderUser $user): bool
    {
        if (blank($user->getEmail())) {
            return false;
        }

        return match ($this) {
            // The driver asks GitHub for the primary address and returns it
            // only once GitHub has verified it.
            self::GitHub => true,
            self::Google => filter_var($user->getRaw()['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
        };
    }
}
