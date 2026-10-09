<?php

namespace App\Enums;

/**
 * The parts of a built CV below the name and contact lines, which the
 * candidate can reorder or leave out of a particular CV. The default
 * order puts projects right after work, where a software CV is read
 * for them.
 */
enum CvSection: string
{
    case Summary = 'summary';
    case Experience = 'experience';
    case Projects = 'projects';
    case Education = 'education';
    case Skills = 'skills';
    case Certifications = 'certifications';

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
        };
    }

    /**
     * @return list<string>
     */
    public static function defaultOrder(): array
    {
        return array_map(fn (self $section) => $section->value, self::cases());
    }
}
