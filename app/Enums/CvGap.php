<?php

namespace App\Enums;

/**
 * Something a CV built from the profile would be missing, and where on
 * the profile it is added. Each one is a suggestion, never a reason to
 * refuse building the CV.
 */
enum CvGap: string
{
    case Phone = 'phone';
    case Location = 'location';
    case Headline = 'headline';
    case Summary = 'summary';
    case Experience = 'experience';
    case RoleDescription = 'role_description';
    case Education = 'education';
    case Skills = 'skills';

    /**
     * @param  ?string  $subject  the role a description is missing from
     */
    public function message(?string $subject = null): string
    {
        return match ($this) {
            self::Phone => __('No phone number, so an employer can only reach you by email.'),
            self::Location => __('No location. Employers look for the city you are based in.'),
            self::Headline => __('No headline under your name.'),
            self::Summary => __('No summary. Two or three lines about you are the first thing a reader sees.'),
            self::Experience => __('No work experience.'),
            self::RoleDescription => __(':role has no description of what you did.', ['role' => $subject]),
            self::Education => __('No education.'),
            self::Skills => __('No skills.'),
        };
    }

    public function route(): string
    {
        return match ($this) {
            self::Phone, self::Location, self::Headline, self::Summary => 'candidate.profile.edit',
            self::Experience, self::RoleDescription => 'candidate.experience.index',
            self::Education => 'candidate.education.index',
            self::Skills => 'candidate.skills.edit',
        };
    }
}
