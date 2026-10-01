<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonInterface;
use DateTimeZone;

/**
 * Which time zone a moment is shown in, and the conversion itself.
 *
 * Every timestamp is stored in UTC, the application's zone, and stays
 * that way: converting happens only when a time is put in front of a
 * person. The zone is the signed-in account's; for a visitor, or an
 * account that has not been seen with a browser yet, it is the one the
 * browser reports in the cookie partials/timezone-cookie writes; failing
 * both, UTC.
 *
 * Dates without a time -- a job's start month, a day's view count -- are
 * calendar dates, not moments, and are never converted.
 */
final class LocalTime
{
    public const COOKIE = 'timezone';

    /**
     * Names IANA has replaced that Chrome, Safari and Node still report,
     * because they take their names from CLDR rather than from IANA. A
     * server's own time zone list may not know the old ones at all --
     * Debian and Ubuntu moved them to a separate tzdata-legacy package --
     * so they are turned into the current name before anything else.
     */
    private const RENAMED = [
        'Africa/Asmera' => 'Africa/Asmara',
        'America/Coral_Harbour' => 'America/Atikokan',
        'America/Godthab' => 'America/Nuuk',
        'Asia/Calcutta' => 'Asia/Kolkata',
        'Asia/Katmandu' => 'Asia/Kathmandu',
        'Asia/Rangoon' => 'Asia/Yangon',
        'Asia/Saigon' => 'Asia/Ho_Chi_Minh',
        'Asia/Ulan_Bator' => 'Asia/Ulaanbaatar',
        'Atlantic/Faeroe' => 'Atlantic/Faroe',
        'Europe/Kiev' => 'Europe/Kyiv',
        'Pacific/Enderbury' => 'Pacific/Kanton',
        'Pacific/Ponape' => 'Pacific/Pohnpei',
        'Pacific/Truk' => 'Pacific/Chuuk',
    ];

    /**
     * @var array<int, string>|null
     */
    private static ?array $identifiers = null;

    public static function zone(): string
    {
        $user = auth()->user();

        if ($user instanceof User && self::isValid($user->timezone)) {
            return $user->timezone;
        }

        return self::fromBrowser() ?? config('app.timezone');
    }

    /**
     * The zone a person is written to in, where there is no browser to
     * ask: an email, a queued job.
     */
    public static function zoneFor(?User $user): string
    {
        return $user !== null && self::isValid($user->timezone)
            ? $user->timezone
            : config('app.timezone');
    }

    /**
     * The zone the current browser reports, under its current name, when
     * it has reported a real one.
     */
    public static function fromBrowser(): ?string
    {
        return self::canonical(request()->cookie(self::COOKIE));
    }

    public static function of(CarbonInterface $moment, ?string $zone = null): CarbonInterface
    {
        return $moment->copy()->setTimezone($zone ?? self::zone());
    }

    /**
     * The current name for a zone this server knows, or null.
     */
    public static function canonical(mixed $zone): ?string
    {
        if (! is_string($zone)) {
            return null;
        }

        $zone = self::RENAMED[$zone] ?? $zone;

        return self::isValid($zone) ? $zone : null;
    }

    public static function isValid(mixed $zone): bool
    {
        return is_string($zone) && in_array($zone, self::identifiers(), true);
    }

    /**
     * Every name this server can convert to, old ones included where its
     * time zone data still carries them.
     *
     * @return array<int, string>
     */
    public static function identifiers(): array
    {
        return self::$identifiers ??= DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC);
    }

    /**
     * Current names only, grouped by region for a select, each labelled
     * with its offset today: "Dhaka (GMT+06:00)" under "Asia".
     *
     * @return array<string, array<string, string>>
     */
    public static function choices(): array
    {
        $choices = [];

        foreach (DateTimeZone::listIdentifiers() as $zone) {
            [$region, $place] = str_contains($zone, '/') ? explode('/', $zone, 2) : ['Other', $zone];
            $choices[$region][$zone] = self::label($zone, str_replace(['_', '/'], [' ', ' / '], $place));
        }

        return $choices;
    }

    /**
     * "Asia/Dhaka (GMT+06:00)". The offset is the one in force at $at,
     * since a zone with summer time has two: a date after the clocks change
     * must not be labelled with today's.
     */
    public static function label(string $zone, ?string $name = null, ?CarbonInterface $at = null): string
    {
        $offset = ($at ?? now())->copy()->setTimezone($zone)->format('P');

        return ($name ?? str_replace('_', ' ', $zone))." (GMT{$offset})";
    }
}
