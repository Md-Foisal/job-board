<?php

namespace App\Services;

use App\Models\CandidateProfile;
use App\Models\JobPosting;
use Illuminate\Support\Collection;

/**
 * The open postings that fit a candidate best, by the same match score
 * search shows -- the reason to fill in skills at all, shown where the
 * candidate lands after signing in.
 *
 * A candidate with no skills gets nothing back rather than a guess:
 * there is nothing to match on, and "recommended" jobs picked at random
 * would teach them the list means nothing. Jobs already applied to are
 * left out; the list is for deciding what to do next.
 */
class JobMatches
{
    /**
     * How many of the newest overlapping postings are scored. Scoring
     * happens in memory, so this bounds the work per dashboard load.
     */
    private const CANDIDATES_TO_SCORE = 100;

    public function __construct(private MatchScoreCalculator $calculator) {}

    /**
     * @return Collection<int, array{jobPosting: JobPosting, score: int}>
     */
    public function for(CandidateProfile $candidateProfile, int $limit = 6): Collection
    {
        $skillIds = $candidateProfile->skills()->pluck('skills.id');

        if ($skillIds->isEmpty()) {
            return collect();
        }

        return JobPosting::query()
            ->active()
            ->whereHas('skills', fn ($query) => $query->whereIn('skills.id', $skillIds))
            ->whereDoesntHave('applications', fn ($query) => $query->where('candidate_profile_id', $candidateProfile->id))
            ->with(['company:id,name,slug,logo_path,verified_at', 'skills:id,name'])
            ->latest('created_at')
            ->limit(self::CANDIDATES_TO_SCORE)
            ->get()
            ->map(fn (JobPosting $jobPosting) => [
                'jobPosting' => $jobPosting,
                'score' => $this->calculator->calculate($jobPosting, $skillIds),
            ])
            // Best fit first; among equals the newer posting, which the
            // query order already gives and a stable sort keeps.
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    /**
     * Scores for postings the page is already showing for another reason,
     * so a card reads the same wherever it appears.
     *
     * @param  Collection<int, JobPosting>  $jobPostings  with skills loaded
     * @return array<int, int|null>
     */
    public function scoresFor(CandidateProfile $candidateProfile, Collection $jobPostings): array
    {
        $skillIds = $candidateProfile->skills()->pluck('skills.id');

        if ($skillIds->isEmpty()) {
            return [];
        }

        return $jobPostings->mapWithKeys(fn (JobPosting $jobPosting) => [
            $jobPosting->id => $this->calculator->calculate($jobPosting, $skillIds),
        ])->all();
    }
}
