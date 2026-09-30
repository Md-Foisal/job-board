<?php

namespace App\Enums;

/**
 * Unknown is its own answer, not a soft "misses": when either side has
 * not said, or the two cannot be compared (different currencies), the
 * breakdown says so instead of guessing.
 */
enum MatchCheckResult: string
{
    case Fits = 'fits';
    case Misses = 'misses';
    case Unknown = 'unknown';
}
