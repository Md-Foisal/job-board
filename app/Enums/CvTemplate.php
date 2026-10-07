<?php

namespace App\Enums;

/**
 * The looks a built CV can take, switched without retyping anything,
 * since every one reads the same profile.
 *
 * Classic and Modern are one column of real text, which applicant
 * tracking systems read reliably. Creative puts contact details, skills
 * and certificates in a side column for people whose CV is itself a
 * sample of their eye; tracking systems can read two columns out of
 * order, so the page says so before anyone picks it.
 */
enum CvTemplate: string
{
    case Classic = 'classic';
    case Modern = 'modern';
    case Creative = 'creative';

    public function label(): string
    {
        return match ($this) {
            self::Classic => 'Classic',
            self::Modern => 'Modern',
            self::Creative => 'Creative',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Classic => 'Black and white, one column. Safest with application systems.',
            self::Modern => 'One column with a colour for the name and headings.',
            self::Creative => 'Two columns with a coloured side panel, for design roles.',
        };
    }

    /**
     * One column of plain text that tracking systems read in order.
     */
    public function readsWellInTrackingSystems(): bool
    {
        return $this !== self::Creative;
    }

    public function usesAccent(): bool
    {
        return $this !== self::Classic;
    }

    public function view(): string
    {
        return 'cv.'.$this->value;
    }
}
