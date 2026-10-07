<?php

namespace App\Enums;

enum IdentityType: string
{
    case Individual = 'individual';
    case Company = 'company';
    case Agency = 'agency';

    /**
     * What a job seeker is told about who is hiring: whether the job is
     * at the company itself or placed by an agency for a client is the
     * distinction that matters to them. The sign-up form asks the same
     * thing in its own words (companies/create).
     */
    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Individual employer',
            self::Company => 'Direct employer',
            self::Agency => 'Recruitment agency',
        };
    }
}
