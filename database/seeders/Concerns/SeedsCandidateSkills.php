<?php

namespace Database\Seeders\Concerns;

use App\Enums\ProficiencyLevel;
use App\Models\CandidateProfile;
use App\Models\Skill;
use Illuminate\Support\Collection;

/**
 * Seeded candidates used to have no skills at all, which quietly turned
 * off the one thing this product is built around: the match percentage is
 * computed from the overlap between a candidate's skills and a posting's,
 * so with one side empty every score was nothing on the candidate side and
 * a flat 0% on the employer side. The feature looked broken in every fresh
 * database, including the one used to look at the thing.
 *
 * Skills are drawn from the same pool the postings draw from, so the
 * overlap is real and the scores land across the range rather than all at
 * one end.
 */
trait SeedsCandidateSkills
{
    protected ?Collection $skillPool = null;

    protected function attachSkills(CandidateProfile $profile, int $min = 3, int $max = 8): void
    {
        $this->skillPool ??= Skill::query()->get(['id']);

        if ($this->skillPool->isEmpty()) {
            return;
        }

        $count = min($this->skillPool->count(), random_int($min, $max));
        $levels = ProficiencyLevel::cases();

        $profile->skills()->syncWithoutDetaching(
            $this->skillPool
                ->random($count)
                ->mapWithKeys(fn (Skill $skill) => [
                    $skill->id => ['proficiency' => $levels[array_rand($levels)]->value],
                ])
                ->all()
        );
    }
}
