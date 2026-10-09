<?php

namespace App\Enums;

/**
 * What a contact message is about, chosen by the sender. It sorts the
 * staff inbox; it does not route anywhere, since one team reads it all.
 */
enum ContactTopic: string
{
    case Account = 'account';
    case JobSeeking = 'job_seeking';
    case Hiring = 'hiring';
    case Report = 'report';
    case Privacy = 'privacy';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Account => 'My account or signing in',
            self::JobSeeking => 'Looking for work',
            self::Hiring => 'Hiring',
            self::Report => 'A scam or something wrong on the site',
            self::Privacy => 'My data and privacy',
            self::Other => 'Something else',
        };
    }
}
