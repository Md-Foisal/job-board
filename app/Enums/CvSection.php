<?php

namespace App\Enums;

/**
 * The parts of a built CV below the name and contact lines, which the
 * candidate can reorder or leave out of a particular CV. The default
 * order puts projects right after work, where a software CV is read
 * for them, and the parts some countries expect and others do not
 * (personal information, references, a declaration) at the end.
 *
 * Every section prints only when it has something in it, so a section
 * the candidate never filled in needs no hiding.
 */
enum CvSection: string
{
    case Summary = 'summary';
    case Experience = 'experience';
    case Projects = 'projects';
    case Education = 'education';
    case Skills = 'skills';
    case Certifications = 'certifications';
    case Languages = 'languages';
    case Awards = 'awards';
    case Volunteering = 'volunteering';
    case Publications = 'publications';
    case Custom = 'custom';
    case Interests = 'interests';
    case Personal = 'personal';
    case References = 'references';
    case Declaration = 'declaration';

    /**
     * The heading printed on the CV, in the words tracking systems look
     * for.
     */
    public function heading(): string
    {
        return match ($this) {
            self::Summary => 'Summary',
            self::Experience => 'Work Experience',
            self::Projects => 'Projects',
            self::Education => 'Education',
            self::Skills => 'Skills',
            self::Certifications => 'Certifications',
            self::Languages => 'Languages',
            self::Awards => 'Awards',
            self::Volunteering => 'Volunteering',
            self::Publications => 'Publications',
            self::Custom => 'Additional Information',
            self::Interests => 'Interests',
            self::Personal => 'Personal Information',
            self::References => 'References',
            self::Declaration => 'Declaration',
        };
    }

    /**
     * The heading on a CV whose headings are in Bengali, in the words
     * Bangladeshi CVs use.
     */
    public function bengaliHeading(): string
    {
        return match ($this) {
            self::Summary => 'সারসংক্ষেপ',
            self::Experience => 'কর্ম-অভিজ্ঞতা',
            self::Projects => 'প্রকল্প',
            self::Education => 'শিক্ষাগত যোগ্যতা',
            self::Skills => 'দক্ষতা',
            self::Certifications => 'সনদ ও প্রশিক্ষণ',
            self::Languages => 'ভাষা',
            self::Awards => 'পুরস্কার ও সম্মাননা',
            self::Volunteering => 'স্বেচ্ছাসেবা',
            self::Publications => 'প্রকাশনা',
            self::Custom => 'অতিরিক্ত তথ্য',
            self::Interests => 'আগ্রহ',
            self::Personal => 'ব্যক্তিগত তথ্য',
            self::References => 'রেফারেন্স',
            self::Declaration => 'ঘোষণা',
        };
    }

    /**
     * The name in the builder's list of sections.
     */
    public function label(): string
    {
        return match ($this) {
            self::Custom => 'Your own sections',
            default => $this->heading(),
        };
    }

    /**
     * Sections filled in on the profile, which companies also see,
     * rather than on this CV only.
     */
    public function isFromProfile(): bool
    {
        return ! in_array($this, [self::Custom, self::Interests, self::Personal, self::References, self::Declaration], true);
    }

    /**
     * @return list<string>
     */
    public static function defaultOrder(): array
    {
        return array_map(fn (self $section) => $section->value, self::cases());
    }
}
