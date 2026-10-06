<?php

namespace App\Support;

use Illuminate\Support\Number;

/**
 * How an amount of money is written: "£72,000", "€14", "US$90,000",
 * "BDT 50,000". International English, so a dollar is "US$", "CA$" or
 * "A$" rather than a bare "$" the reader has to guess at -- a job can be
 * anywhere -- and a currency without a symbol of its own in English shows
 * its code. Whole units only; pay is stored without minor units.
 */
final class Money
{
    private const LOCALE = 'en_001';

    public static function format(?string $currency, int $amount): string
    {
        $code = strtoupper(trim((string) $currency));

        if ($code === '') {
            return number_format($amount);
        }

        return (string) Number::currency($amount, in: $code, locale: self::LOCALE, precision: 0);
    }

    /**
     * "£72,000–£90,000", "From £72,000", "Up to £90,000", or null when
     * neither end is given.
     */
    public static function range(?string $currency, ?int $min, ?int $max): ?string
    {
        return match (true) {
            $min === null && $max === null => null,
            $min === $max => self::format($currency, $min),
            $max === null => __('From :amount', ['amount' => self::format($currency, $min)]),
            $min === null => __('Up to :amount', ['amount' => self::format($currency, $max)]),
            default => self::format($currency, $min).'–'.self::format($currency, $max),
        };
    }
}
