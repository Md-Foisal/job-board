<?php

namespace App\Support;

use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Currencies;
use Symfony\Component\Intl\Exception\MissingResourceException;

/**
 * The currencies pay can be stated in: every currency that is legal tender
 * somewhere today, according to the Unicode CLDR data Symfony ships.
 *
 * The full ISO 4217 code list is not usable as it stands. Symfony keeps
 * withdrawn currencies for old records (RUR, XEU, AOR, the Croatian kuna),
 * and the current ISO list itself carries fund codes such as USN or CLF,
 * precious metals, and XTS and XXX, which nobody is paid in. CLDR marks
 * those as not legal tender and dates every withdrawal, so when a country
 * changes its currency the list follows on the next composer update with
 * nothing to edit here.
 */
final class SalaryCurrencies
{
    /** @var array{date: string, codes: array<int, string>}|null */
    private static ?array $memo = null;

    /**
     * Sorted by code, the order type-ahead in a select works in.
     *
     * The list depends on the date, so a long-running worker recomputes it
     * once the day changes.
     *
     * @return array<int, string>
     */
    public static function codes(): array
    {
        $today = gmdate('Y-m-d');

        if (self::$memo === null || self::$memo['date'] !== $today) {
            self::$memo = ['date' => $today, 'codes' => self::inUseToday()];
        }

        return self::$memo['codes'];
    }

    public static function isInUse(?string $code): bool
    {
        return $code !== null && in_array($code, self::codes(), true);
    }

    /**
     * Options for a select, "USD — US Dollar". A stored code that has since
     * gone out of use is kept and marked, so an old posting opens showing
     * what it says rather than silently showing another currency.
     *
     * @return array<string, string>
     */
    public static function options(?string $keep = null): array
    {
        $options = [];

        foreach (self::codes() as $code) {
            $options[$code] = $code.' — '.self::name($code);
        }

        if (filled($keep) && ! isset($options[$keep])) {
            $options = [$keep => Currencies::exists($keep)
                ? __(':code — :name (no longer in use)', ['code' => $keep, 'name' => self::name($keep)])
                : $keep] + $options;
        }

        return $options;
    }

    private static function name(string $code): string
    {
        return Currencies::getName($code, app()->getLocale());
    }

    /**
     * @return array<int, string>
     */
    private static function inUseToday(): array
    {
        $codes = [];

        foreach (Countries::getCountryCodes() as $country) {
            try {
                array_push($codes, ...Currencies::forCountry($country));
            } catch (MissingResourceException) {
                // A territory with no currency of its own on record.
            }
        }

        $codes = array_values(array_unique($codes));
        sort($codes);

        return $codes;
    }
}
