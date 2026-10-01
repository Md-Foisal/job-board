<?php

namespace App\Services;

use App\Enums\MatchCheck;
use App\Enums\MatchCheckResult;
use App\Enums\SkillImportance;
use App\Models\CandidatePreference;
use App\Models\CandidateProfile;
use App\Models\JobPosting;
use App\Models\Skill;
use App\Support\ExperienceDuration;
use App\Support\MatchBreakdown;
use BackedEnum;
use Illuminate\Support\Collection;

/**
 * The product's signature candidate-facing match score: how well a
 * candidate's own skills overlap with a job posting's required/preferred
 * skills. Deliberately a plain skill-intersection calculation, not an
 * AI call -- the AI-powered enhancement layer (stur 6) sits on top of
 * this later without replacing it.
 *
 * "Required" skills count double toward the match -- missing one of
 * those should hurt the score more than missing a "nice to have".
 */
class MatchScoreCalculator
{
    /**
     * @param  Collection<int, int>  $candidateSkillIds  the candidate's own skill IDs, loaded once by the caller
     */
    public function calculate(JobPosting $jobPosting, Collection $candidateSkillIds): ?int
    {
        $jobSkills = $jobPosting->skills;

        // No score rather than a zero, on either side. A candidate who has
        // never listed a skill is not a 0% fit -- there is simply nothing
        // to compare -- and "0% match" beside their name reads as a
        // verdict an employer would act on. The candidate-facing side
        // already declines to score in this case; this is the same rule
        // from the other direction.
        if ($jobSkills->isEmpty() || $candidateSkillIds->isEmpty()) {
            return null;
        }

        $totalWeight = 0;
        $matchedWeight = 0;

        foreach ($jobSkills as $skill) {
            $weight = $skill->pivot->importance === SkillImportance::Required ? 2 : 1;
            $totalWeight += $weight;

            if ($candidateSkillIds->contains($skill->id)) {
                $matchedWeight += $weight;
            }
        }

        return (int) round(($matchedWeight / $totalWeight) * 100);
    }

    /**
     * The score with its reasons, for the candidate looking at one job:
     * which skills matched or are missing, and how the posting sits with
     * their own expectations and experience. The score is calculate()'s,
     * unchanged.
     */
    public function breakdown(JobPosting $jobPosting, CandidateProfile $candidateProfile): MatchBreakdown
    {
        $candidateSkillIds = $candidateProfile->skills()->pluck('skills.id');
        $experience = $candidateProfile->experienceRecords;
        $experienceMonths = $experience->isEmpty() ? null : ExperienceDuration::months($experience);
        $preference = $candidateProfile->preference;

        [$required, $niceToHave] = $jobPosting->skills
            ->partition(fn (Skill $skill) => $skill->pivot->importance === SkillImportance::Required);

        $isMatched = fn (Skill $skill) => $candidateSkillIds->contains($skill->id);

        return new MatchBreakdown(
            score: $this->calculate($jobPosting, $candidateSkillIds),
            matchedRequired: $required->filter($isMatched)->values(),
            matchedNiceToHave: $niceToHave->filter($isMatched)->values(),
            missingRequired: $required->reject($isMatched)->values(),
            missingNiceToHave: $niceToHave->reject($isMatched)->values(),
            checks: [
                MatchCheck::Salary->value => $this->salary($jobPosting, $preference),
                MatchCheck::Workplace->value => $this->same($preference?->preferred_workplace_type, $jobPosting->workplace_type),
                MatchCheck::Employment->value => $this->same($preference?->preferred_employment_type, $jobPosting->employment_type),
                MatchCheck::Experience->value => $this->experience($jobPosting, $experienceMonths),
            ],
            experienceMonths: $experienceMonths,
            profileIsEmpty: $candidateSkillIds->isEmpty()
                && $experience->isEmpty()
                && $preference?->desired_salary_min === null
                && $preference?->preferred_workplace_type === null
                && $preference?->preferred_employment_type === null,
        );
    }

    /**
     * Fits when the posting's top reaches the candidate's floor. With no
     * top given, only a posting that already starts at or above the floor
     * is known to fit; anything lower might still stretch, so it is
     * unknown, and so is a negotiable salary whatever figures it carries.
     * Amounts compare as monthly, the period the candidate's
     * preference is asked in, and only in the same currency -- there is
     * no conversion, so different currencies are unknown too.
     */
    private function salary(JobPosting $jobPosting, ?CandidatePreference $preference): MatchCheckResult
    {
        $floor = $preference?->desired_salary_min;
        $candidateCurrency = strtoupper(trim((string) $preference?->desired_salary_currency));
        $jobCurrency = strtoupper(trim((string) $jobPosting->salary_currency));

        if ($jobPosting->salary_negotiable
            || $floor === null
            || $candidateCurrency === ''
            || $candidateCurrency !== $jobCurrency) {
            return MatchCheckResult::Unknown;
        }

        if ($jobPosting->salary_max_monthly !== null) {
            return $floor <= $jobPosting->salary_max_monthly ? MatchCheckResult::Fits : MatchCheckResult::Misses;
        }

        if ($jobPosting->salary_min_monthly !== null && $jobPosting->salary_min_monthly >= $floor) {
            return MatchCheckResult::Fits;
        }

        return MatchCheckResult::Unknown;
    }

    /**
     * An exact match only: a candidate who wants remote work is not
     * served by a hybrid posting. No preference means nothing to compare.
     */
    private function same(?BackedEnum $wanted, BackedEnum $offered): MatchCheckResult
    {
        if ($wanted === null) {
            return MatchCheckResult::Unknown;
        }

        return $wanted === $offered ? MatchCheckResult::Fits : MatchCheckResult::Misses;
    }

    /**
     * An empty work history is unknown rather than a miss, the same rule
     * the score follows for an empty skill list: the candidate may simply
     * not have filled it in. A posting asking for no experience fits
     * anyone.
     */
    private function experience(JobPosting $jobPosting, ?int $experienceMonths): MatchCheckResult
    {
        $years = $jobPosting->min_experience_years;

        if ($years === null) {
            return MatchCheckResult::Unknown;
        }

        if ($years === 0) {
            return MatchCheckResult::Fits;
        }

        if ($experienceMonths === null) {
            return MatchCheckResult::Unknown;
        }

        return $experienceMonths >= $years * 12 ? MatchCheckResult::Fits : MatchCheckResult::Misses;
    }
}
