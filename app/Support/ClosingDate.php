<?php

namespace App\Support;

use App\Models\Company;
use App\Models\JobPosting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * A posting's closing date is a day the employer picks -- "accept
 * applications until 30 October" -- and it means the whole of that day
 * in the company's own time zone. It is stored as the last second of that
 * day, in UTC like every other moment, so the plain `expires_at > now()`
 * checks everywhere keep working unchanged.
 */
final class ClosingDate
{
    /**
     * The end of $date (Y-m-d) in the company's zone.
     */
    public static function endOf(string $date, Company $company): CarbonImmutable
    {
        return CarbonImmutable::parse($date, $company->timezone)->endOfDay()->utc();
    }

    /**
     * A month on from the current closing day while it is still ahead,
     * otherwise a month from today -- a posting that lapsed last month
     * should get a full month, not three days. No overflow: a month after
     * 31 January is the end of February, not 3 March.
     */
    public static function monthAfter(Company $company, ?CarbonInterface $current = null): CarbonImmutable
    {
        $from = $current !== null && $current->isFuture() ? $current : now();

        return CarbonImmutable::instance($from)
            ->setTimezone($company->timezone)
            ->addMonthNoOverflow()
            ->endOfDay()
            ->utc();
    }

    /**
     * The closing day as the company picked it, for the date input and
     * for "Closes 30 Oct" on the company's own pages. A page that lists
     * one company's postings passes the company, rather than loading it
     * again for every row.
     */
    public static function day(JobPosting $jobPosting, ?Company $company = null): CarbonImmutable
    {
        return CarbonImmutable::instance($jobPosting->expires_at)->setTimezone(($company ?? $jobPosting->company)->timezone);
    }

    /**
     * Today in the company's zone, as Y-m-d: a closing date must be later.
     */
    public static function today(Company $company): string
    {
        return CarbonImmutable::now($company->timezone)->toDateString();
    }
}
