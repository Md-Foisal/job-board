<?php

namespace App\Support;

use Illuminate\Support\Str;
use Symfony\Component\Intl\Countries as IntlCountries;

/**
 * Countries by their ISO 3166-1 two-letter code, from the Unicode CLDR
 * data Symfony ships, and a way to read a country someone typed.
 *
 * A candidate picks their country from a list, so it is stored as a code.
 * A job posting's country is typed by the employer, so to compare the two
 * the typed name is read back to a code: the English name, either ISO
 * code, or one of the short forms people commonly write ("UK", "USA").
 * Text that names no country reads as null, and the comparison then says
 * it cannot tell rather than guessing.
 */
final class Countries
{
    /**
     * Names people write that are not CLDR's English name for the country.
     *
     * @var array<string, string>
     */
    private const OTHER_NAMES = [
        'uk' => 'GB',
        'great britain' => 'GB',
        'britain' => 'GB',
        'england' => 'GB',
        'scotland' => 'GB',
        'wales' => 'GB',
        'northern ireland' => 'GB',
        'usa' => 'US',
        'united states of america' => 'US',
        'uae' => 'AE',
        'holland' => 'NL',
        'czech republic' => 'CZ',
        'turkey' => 'TR',
        'ivory coast' => 'CI',
        'burma' => 'MM',
        'korea' => 'KR',
        'republic of korea' => 'KR',
    ];

    /** @var array<string, string>|null */
    private static ?array $byName = null;

    /**
     * Options for a select, "Bangladesh" under BD, in the order of their
     * names. A stored code no longer in the data is kept, so the form
     * shows what was saved instead of silently showing another country.
     *
     * @return array<string, string>
     */
    public static function options(?string $keep = null): array
    {
        $options = IntlCountries::getNames(app()->getLocale());

        if (filled($keep) && ! isset($options[$keep])) {
            $options = [$keep => $keep] + $options;
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return IntlCountries::getCountryCodes();
    }

    public static function name(?string $code): ?string
    {
        if (blank($code)) {
            return null;
        }

        return IntlCountries::exists($code) ? IntlCountries::getName($code, app()->getLocale()) : $code;
    }

    /**
     * The code of the country a piece of free text names, or null.
     */
    public static function resolve(?string $text): ?string
    {
        $key = self::normalise($text);

        if ($key === '') {
            return null;
        }

        $upper = strtoupper($key);

        if (strlen($upper) === 2 && IntlCountries::exists($upper)) {
            return $upper;
        }

        if (strlen($upper) === 3 && IntlCountries::alpha3CodeExists($upper)) {
            return IntlCountries::getAlpha2Code($upper);
        }

        return self::byName()[$key] ?? self::OTHER_NAMES[$key] ?? null;
    }

    /**
     * Lower case, without accents, full stops, a leading "the" or extra
     * spaces: "The Netherlands", "U.K." and "Türkiye" read as written
     * in the list.
     */
    private static function normalise(?string $text): string
    {
        $text = Str::of((string) $text)->ascii()->lower()->replace('.', '')->squish()->value();

        return preg_replace('/^the /', '', $text);
    }

    /**
     * @return array<string, string>
     */
    private static function byName(): array
    {
        if (self::$byName === null) {
            self::$byName = [];

            foreach (IntlCountries::getNames('en') as $code => $name) {
                self::$byName[self::normalise($name)] = $code;
            }
        }

        return self::$byName;
    }
}
