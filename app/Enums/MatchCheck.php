<?php

namespace App\Enums;

/**
 * What a match breakdown compares besides skills: the candidate's own
 * expectations, where they live and their experience, against what the
 * posting offers and asks. Shown to the candidate only, beside the score,
 * never folded into it.
 */
enum MatchCheck: string
{
    case Salary = 'salary';
    case Workplace = 'workplace';
    case Location = 'location';
    case Employment = 'employment';
    case Experience = 'experience';

    public function label(): string
    {
        return match ($this) {
            self::Salary => 'Salary',
            self::Workplace => 'Workplace',
            self::Location => 'Location',
            self::Employment => 'Employment type',
            self::Experience => 'Experience',
        };
    }
}
