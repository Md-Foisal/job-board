<?php

namespace App\Enums;

/**
 * How well a candidate speaks a language: native, or a level on the
 * Common European Framework of Reference (CEFR), the scale Europass and
 * most employers outside the US read. The strongest first.
 */
enum LanguageLevel: string
{
    case Native = 'native';
    case C2 = 'c2';
    case C1 = 'c1';
    case B2 = 'b2';
    case B1 = 'b1';
    case A2 = 'a2';
    case A1 = 'a1';

    public function label(): string
    {
        return match ($this) {
            self::Native => 'Native',
            self::C2 => 'Proficient (C2)',
            self::C1 => 'Advanced (C1)',
            self::B2 => 'Upper intermediate (B2)',
            self::B1 => 'Intermediate (B1)',
            self::A2 => 'Elementary (A2)',
            self::A1 => 'Beginner (A1)',
        };
    }

    public function bengaliLabel(): string
    {
        return match ($this) {
            self::Native => 'মাতৃভাষা',
            self::C2 => 'দক্ষ (C2)',
            self::C1 => 'উচ্চতর (C1)',
            self::B2 => 'উচ্চ-মাঝারি (B2)',
            self::B1 => 'মাঝারি (B1)',
            self::A2 => 'প্রাথমিক (A2)',
            self::A1 => 'শুরুর স্তর (A1)',
        };
    }

    public function rank(): int
    {
        return count(self::cases()) - array_search($this, self::cases(), true);
    }
}
