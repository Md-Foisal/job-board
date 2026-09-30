<?php

namespace App\Support;

use App\Ai\Agents\MatchExplainer;
use App\Enums\MatchCheck;
use App\Enums\SkillImportance;
use App\Models\CandidateProfile;
use App\Models\EducationRecord;
use App\Models\ExperienceRecord;
use App\Models\JobPosting;
use App\Models\Skill;

/**
 * What is sent to the AI to explain a match, and the cache key its answer
 * is kept under.
 *
 * Only what the explanation needs goes out: the job, the candidate's
 * professional profile, and the product's own breakdown. Never the name,
 * email, phone, photo, links, CV file or salary expectations -- the
 * salary check is left out of the facts for the same reason.
 *
 * The key hashes all of it with the prompt's version, so an explanation
 * is shown again only while the job, the profile and the prompt are the
 * same as when it was written.
 */
final class MatchExplanationInput
{
    /**
     * Enough for any real job description; the rest is cut so one very
     * long posting cannot make a run expensive.
     */
    private const DESCRIPTION_MAX = 12000;

    private const PROFILE_TEXT_MAX = 2000;

    /**
     * @return array{job_posting: array<string, mixed>, candidate_profile: array<string, mixed>, match_facts: array<string, mixed>}
     */
    public static function for(JobPosting $jobPosting, CandidateProfile $candidateProfile, MatchBreakdown $breakdown): array
    {
        return [
            'job_posting' => [
                'title' => $jobPosting->title,
                'employment_type' => $jobPosting->employment_type->label(),
                'workplace_type' => $jobPosting->workplace_type->label(),
                'min_experience_years' => $jobPosting->min_experience_years,
                'required_skills' => self::names($jobPosting->skills->filter(fn (Skill $skill) => $skill->pivot->importance === SkillImportance::Required)),
                'nice_to_have_skills' => self::names($jobPosting->skills->reject(fn (Skill $skill) => $skill->pivot->importance === SkillImportance::Required)),
                'description' => self::plain($jobPosting->description, self::DESCRIPTION_MAX),
            ],
            'candidate_profile' => [
                'headline' => $candidateProfile->headline,
                'about' => self::plain($candidateProfile->bio, self::PROFILE_TEXT_MAX),
                'skills' => $candidateProfile->skills()->orderBy('name')->pluck('name')->all(),
                'experience' => $candidateProfile->experienceRecords()->orderByDesc('start_date')->get()
                    ->map(fn (ExperienceRecord $record) => [
                        'job_title' => $record->job_title,
                        'company' => $record->company_name,
                        'from' => $record->start_date->format('Y-m'),
                        'to' => $record->end_date?->format('Y-m') ?? 'present',
                        'description' => self::plain($record->description, self::PROFILE_TEXT_MAX),
                    ])->all(),
                'education' => $candidateProfile->educationRecords()->orderByDesc('start_date')->get()
                    ->map(fn (EducationRecord $record) => [
                        'institution' => $record->institution_name,
                        'degree' => $record->degree,
                        'field_of_study' => $record->field_of_study,
                        'from' => $record->start_date->format('Y-m'),
                        'to' => $record->end_date?->format('Y-m') ?? 'present',
                    ])->all(),
            ],
            'match_facts' => [
                'required_skills_matched' => self::names($breakdown->matchedRequired),
                'required_skills_missing' => self::names($breakdown->missingRequired),
                'nice_to_have_skills_matched' => self::names($breakdown->matchedNiceToHave),
                'nice_to_have_skills_missing' => self::names($breakdown->missingNiceToHave),
                'workplace_type_fits_their_preference' => $breakdown->check(MatchCheck::Workplace)->value,
                'employment_type_fits_their_preference' => $breakdown->check(MatchCheck::Employment)->value,
                'experience_meets_the_minimum' => $breakdown->check(MatchCheck::Experience)->value,
                'total_experience_months' => $breakdown->experienceMonths,
            ],
        ];
    }

    /**
     * The whole input as one JSON object, which is what the model receives.
     * Whatever an employer or candidate typed arrives as a quoted string
     * inside it; angle brackets are escaped as well, so nothing in it can
     * read as markup around it.
     *
     * @param  array<string, array<string, mixed>>  $input
     */
    public static function material(array $input): string
    {
        return json_encode($input, JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function cacheKey(CandidateProfile $candidateProfile, JobPosting $jobPosting, array $input): string
    {
        $hash = hash('sha256', MatchExplainer::VERSION.'|'.json_encode($input, JSON_THROW_ON_ERROR));

        return "ai-match:{$candidateProfile->id}:{$jobPosting->id}:{$hash}";
    }

    /**
     * @param  iterable<int, Skill>  $skills
     * @return array<int, string>
     */
    private static function names(iterable $skills): array
    {
        return collect($skills)->pluck('name')->values()->all();
    }

    /**
     * Stored rich text as plain text: block ends become line breaks, tags
     * and entities go, and runs of blank space shrink.
     */
    private static function plain(?string $html, int $max): ?string
    {
        if (blank($html)) {
            return null;
        }

        $text = preg_replace('~<\s*(?:br\s*/?|/p|/li|/h[1-6]|/div|/blockquote)\s*>~i', "\n", $html) ?? '';
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace(['/[ \t]+/u', '/\n\s*\n\s*/u'], [' ', "\n\n"], $text) ?? '';
        $text = trim($text);

        return $text === '' ? null : mb_substr($text, 0, $max);
    }
}
