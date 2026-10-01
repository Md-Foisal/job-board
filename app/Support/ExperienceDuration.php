<?php

namespace App\Support;

use App\Models\ExperienceRecord;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * How long a candidate has worked in total, in whole months.
 *
 * Counted in calendar months, both ends included, because that is how a
 * CV and the profile state a role ("Jan 2021 – Dec 2021" is a year). Two
 * roles held at the same time count once, so a side job does not double
 * the total. A role still going (no end date) runs to this month; a date
 * in the future counts only up to this month.
 */
final class ExperienceDuration
{
    /**
     * @param  Collection<int, ExperienceRecord>  $records
     */
    public static function months(Collection $records, ?CarbonInterface $today = null): int
    {
        $now = self::index($today ?? now());

        $spans = $records
            ->map(fn (ExperienceRecord $record) => [
                self::index($record->start_date),
                min($record->end_date ? self::index($record->end_date) : $now, $now),
            ])
            ->filter(fn (array $span) => $span[0] <= $span[1])
            ->sortBy(fn (array $span) => $span[0])
            ->values();

        $total = 0;
        $current = null;

        foreach ($spans as [$start, $end]) {
            // Overlapping or back to back: the same stretch of working time.
            if ($current !== null && $start <= $current[1] + 1) {
                $current[1] = max($current[1], $end);

                continue;
            }

            if ($current !== null) {
                $total += $current[1] - $current[0] + 1;
            }

            $current = [$start, $end];
        }

        if ($current !== null) {
            $total += $current[1] - $current[0] + 1;
        }

        return $total;
    }

    /**
     * Months since year zero, so two dates compare and subtract as months.
     */
    private static function index(CarbonInterface $date): int
    {
        return $date->year * 12 + $date->month - 1;
    }
}
