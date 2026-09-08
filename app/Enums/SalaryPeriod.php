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
}
