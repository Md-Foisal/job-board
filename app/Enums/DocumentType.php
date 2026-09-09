<?php

namespace App\Enums;

enum DocumentType: string
{
    case Cv = 'cv';
    case WorkSample = 'work_sample';
    case Certificate = 'certificate';

    public function label(): string
    {
        return match ($this) {
            self::Cv => 'CV',
            self::WorkSample => 'Work sample',
            self::Certificate => 'Certificate',
        };
    }
}
