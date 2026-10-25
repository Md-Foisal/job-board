<?php

namespace App\Enums;

/**
 * The looks a built CV can take, switched without retyping anything,
 * since every one reads the same profile. Listed with the ones tracking
 * systems read safely first.
 *
 * All but Creative are one reading order of real text, which applicant
 * tracking systems read reliably, however they look: Timeline only
 * places each heading beside its entries, and Bold's coloured band is
 * part of the page's text. Creative puts contact details, skills and
 * certificates in a side column for people whose CV is itself a sample
 * of their eye; tracking systems can read two columns out of order, so
 * the page says so before anyone picks it.
 */
enum CvTemplate: string
{
    case Classic = 'classic';
    case Modern = 'modern';
    case Compact = 'compact';
    case Executive = 'executive';
    case Timeline = 'timeline';
    case Bold = 'bold';
    case Creative = 'creative';

    public function label(): string
    {
        return match ($this) {
            self::Classic => 'Classic',
            self::Modern => 'Modern',
            self::Compact => 'Compact',
            self::Executive => 'Executive',
            self::Timeline => 'Timeline',
            self::Bold => 'Bold',
            self::Creative => 'Creative',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Classic => 'Black and white, one column. Safest with application systems.',
            self::Modern => 'One column with a colour for the name and headings.',
            self::Compact => 'Smaller type, each role on one line. Fits a long history on one page.',
            self::Executive => 'Centred serif letterhead and ruled headings. For formal and senior roles.',
            self::Timeline => 'Section names in a narrow column beside each entry.',
            self::Bold => 'Your name and contact on a band of colour across the top.',
            self::Creative => 'Two columns with a coloured side panel, for design roles.',
        };
    }

    /**
     * One reading order of plain text that tracking systems follow.
     */
    public function readsWellInTrackingSystems(): bool
    {
        return $this !== self::Creative;
    }

    public function usesAccent(): bool
    {
        return $this !== self::Classic;
    }

    /**
     * The typeface the template is drawn for, used until the candidate
     * picks another.
     */
    public function font(): CvFont
    {
        return match ($this) {
            self::Classic => CvFont::SourceSans,
            self::Modern, self::Compact, self::Bold => CvFont::Inter,
            self::Executive => CvFont::SourceSerif,
            self::Timeline, self::Creative => CvFont::Lato,
        };
    }

    public function view(): string
    {
        return 'cv.'.$this->value;
    }
}
