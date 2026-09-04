<?php

namespace App\Enums;

enum SkillImportance: string
{
    case Required = 'required';
    case NiceToHave = 'nice-to-have';

    public function label(): string
    {
        return match ($this) {
            self::Required => 'Required',
            self::NiceToHave => 'Nice to Have',
        };
    }
}
