<?php

namespace App\Enums;

enum SalaryPeriod: string
{
    case Hourly = 'hourly';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Hourly => 'Hourly',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
            self::Yearly => 'Yearly',
        };
    }

    /**
     * How the period follows an amount: "£72,000 a year".
     */
    public function per(): string
    {
        return match ($this) {
            self::Hourly => __('an hour'),
            self::Weekly => __('a week'),
            self::Monthly => __('a month'),
            self::Yearly => __('a year'),
        };
    }
}
