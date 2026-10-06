<?php

namespace App\Support;

/**
 * The one way each kind of date is written across the product, so the
 * same day never reads "Nov 5, 2026" on one page and "5 November" on the
 * next. Day, then the month as a word, then the year (GOV.UK's style:
 * "4 June 2017", months shortened where space is tight) reads the same
 * to someone in London, Dhaka or New York, where 05/11 would not.
 *
 * How long ago something happened ("4 hours ago") is a separate choice,
 * made where freshness is the point, such as a job card or an activity
 * log.
 */
final class DateFormat
{
    /** 5 Nov 2026 */
    public const DAY = 'j M Y';

    /** Nov 2026: spans of work and study, the month of a review. */
    public const MONTH = 'M Y';

    /** 5 Nov 2026, 2:27 pm: a moment, once it is in the reader's zone. */
    public const MOMENT = 'j M Y, g:i a';

    /** 5 Nov: chart axes, where the year is given once nearby. */
    public const DAY_SHORT = 'j M';
}
