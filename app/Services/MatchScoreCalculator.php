<?php

namespace App\Services;

use App\Enums\SkillImportance;
use App\Models\JobPosting;
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
}
