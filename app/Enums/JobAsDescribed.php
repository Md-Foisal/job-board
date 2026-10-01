<?php

namespace App\Enums;

/**
 * A reviewer's answer to "was the job what the posting said it was?".
 * Not sure is a real answer rather than a skipped one: someone turned
 * down after a first call may never have learned enough to say.
 */
enum JobAsDescribed: string
{
    case Yes = 'yes';
    case No = 'no';
    case NotSure = 'not_sure';

    public function label(): string
    {
        return match ($this) {
            self::Yes => 'Yes',
            self::No => 'No',
            self::NotSure => 'Not sure',
        };
    }
}
