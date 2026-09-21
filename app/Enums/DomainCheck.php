<?php

namespace App\Enums;

/**
 * What comparing a company's website with its managers' email addresses
 * says -- a signal for the reviewer, never a verdict on its own.
 */
enum DomainCheck: string
{
    case Match = 'match';
    case Mismatch = 'mismatch';
    case PersonalEmail = 'personal_email';
    case NoWebsite = 'no_website';

    public function label(): string
    {
        return match ($this) {
            self::Match => 'Email matches website',
            self::Mismatch => 'Email does not match website',
            self::PersonalEmail => 'Personal email only',
            self::NoWebsite => 'No website given',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Match => 'success',
            self::Mismatch => 'danger',
            self::PersonalEmail, self::NoWebsite => 'warning',
        };
    }
}
