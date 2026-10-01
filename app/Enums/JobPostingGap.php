<?php

namespace App\Enums;

/**
 * Something about a posting that tends to cost it applicants, or brings
 * the wrong ones. Each is a suggestion to the employer, never a rule the
 * posting has to pass.
 */
enum JobPostingGap: string
{
    case NoSalary = 'no_salary';
    case TooManyRequiredSkills = 'too_many_required_skills';
    case ShortDescription = 'short_description';
    case LongDescription = 'long_description';
    case ExpiringSoon = 'expiring_soon';
    case LowApplyRate = 'low_apply_rate';
    case ManyRejectedUnseen = 'many_rejected_unseen';

    public function message(): string
    {
        return match ($this) {
            self::NoSalary => __('No salary is shown. Many people skip postings that do not say what they pay.'),
            self::TooManyRequiredSkills => __('A long list of required skills turns away people who have most of them.'),
            self::ShortDescription => __('The description is short. Say what the work is, who it is with, and what success looks like.'),
            self::LongDescription => __('The description is long. The parts that decide whether someone applies may be getting lost.'),
            self::ExpiringSoon => __('This posting closes within a few days.'),
            self::LowApplyRate => __('Plenty of people are reading this posting, but few of them apply.'),
            self::ManyRejectedUnseen => __('Many applications are rejected without being looked at. The requirements may not be saying who you want.'),
        };
    }
}
