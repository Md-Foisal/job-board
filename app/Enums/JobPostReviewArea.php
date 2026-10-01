<?php

namespace App\Enums;

/**
 * The part of a job posting one AI review point is about.
 */
enum JobPostReviewArea: string
{
    case Title = 'title';
    case Salary = 'salary';
    case Requirements = 'requirements';
    case Description = 'description';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Title => 'Title',
            self::Salary => 'Salary',
            self::Requirements => 'Requirements',
            self::Description => 'Description',
            self::Other => 'Other',
        };
    }
}
