<?php

namespace App\Support;

/**
 * The rules for a candidate's phone number and location, in one place, so
 * the profile form and a CV reading accept exactly the same values.
 *
 * Both are free text meant to be printed on a CV, not dialled or matched
 * on. A phone number is checked only for its shape, the way people write
 * one: an optional leading plus, bracketed or not, then digits with
 * spaces, brackets, dots, dashes or slashes between them. It must have
 * 5 to 20 digits; the ITU allows 15, but Google's libphonenumber has
 * found 17-digit national numbers in Germany, plus up to 3 for the
 * country code.
 */
final class ContactDetails
{
    public const PHONE_MAX = 30;

    public const LOCATION_MAX = 100;

    private const PHONE_PATTERN = '/^\(?\+?[ ().\/-]*(?:\d[ ().\/-]*){5,20}$/';

    /**
     * @return array<int, string>
     */
    public static function phoneRules(): array
    {
        return ['nullable', 'string', 'max:'.self::PHONE_MAX, 'regex:'.self::PHONE_PATTERN];
    }

    /**
     * @return array<int, string>
     */
    public static function locationRules(): array
    {
        return ['nullable', 'string', 'max:'.self::LOCATION_MAX];
    }

    public static function isPhone(string $value): bool
    {
        return mb_strlen($value) <= self::PHONE_MAX && preg_match(self::PHONE_PATTERN, $value) === 1;
    }
}
