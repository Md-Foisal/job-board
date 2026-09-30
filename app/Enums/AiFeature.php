<?php

namespace App\Enums;

/**
 * Every task the product hands to an AI model. The value is what
 * ai_usages stores and what config/plans.php keys its monthly limits by.
 */
enum AiFeature: string
{
    case ResumeParser = 'resume_parser';
    case MatchExplanation = 'match_explanation';
    case CvBuilder = 'cv_builder';
    case JobPostReview = 'job_post_review';
    case ReviewScreening = 'review_screening';

    /**
     * Whose plan pays for a run.
     *
     * A job post review is the company's tool, so its allowance is shared
     * by the team and does not grow with every teammate added. Review
     * screening is the platform moderating itself: nobody asked for it, so
     * no one's allowance is spent on it.
     */
    public function payer(): AiPayer
    {
        return match ($this) {
            self::ResumeParser, self::MatchExplanation, self::CvBuilder => AiPayer::User,
            self::JobPostReview => AiPayer::Company,
            self::ReviewScreening => AiPayer::Platform,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ResumeParser => 'Resume import',
            self::MatchExplanation => 'Match explanation',
            self::CvBuilder => 'CV writing suggestions',
            self::JobPostReview => 'Job post review',
            self::ReviewScreening => 'Review screening',
        };
    }
}
