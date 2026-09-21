<?php

namespace App\Actions;

use App\Enums\SkillImportance;
use App\Models\JobAlert;
use App\Models\Skill;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Folding a duplicate skill into the one it duplicates -- "ReactJS" into
 * "React".
 *
 * Every posting and every candidate that used the duplicate is moved
 * across before it is removed. A merge that left them behind would do
 * exactly the damage the curated list exists to prevent: the candidate
 * who wrote "ReactJS" would silently stop matching the posting that
 * asked for "React". Where someone already had both, the stronger claim
 * wins -- required over nice-to-have, the higher proficiency over the
 * lower -- so no one's match score goes down because of the merge.
 */
class MergeSkill
{
    private const PROFICIENCY_RANK = [
        'beginner' => 1,
        'intermediate' => 2,
        'advanced' => 3,
    ];

    public function __invoke(Skill $from, Skill $into): void
    {
        if ($from->is($into) || $into->trashed()) {
            throw new InvalidArgumentException('A skill can only be merged into a different skill that is still in use.');
        }

        DB::transaction(function () use ($from, $into) {
            $this->move('job_posting_skill', 'job_posting_id', 'importance', $from, $into,
                fn (string $a, string $b) => in_array(SkillImportance::Required->value, [$a, $b], true)
                    ? SkillImportance::Required->value
                    : $b);

            $this->move('candidate_profile_skill', 'candidate_profile_id', 'proficiency', $from, $into,
                fn (string $a, string $b) => (self::PROFICIENCY_RANK[$a] ?? 0) >= (self::PROFICIENCY_RANK[$b] ?? 0) ? $a : $b);

            // Same reason for job alerts: one that asked for "ReactJS"
            // would otherwise stop matching anything at all.
            JobAlert::where('criteria->skill', $from->id)
                ->update(['criteria->skill' => $into->id]);

            $from->delete();
        });
    }

    /**
     * @param  callable(string, string): string  $stronger
     */
    private function move(string $table, string $ownerColumn, string $levelColumn, Skill $from, Skill $into, callable $stronger): void
    {
        foreach (DB::table($table)->where('skill_id', $from->id)->get() as $row) {
            $existing = DB::table($table)
                ->where($ownerColumn, $row->{$ownerColumn})
                ->where('skill_id', $into->id)
                ->first();

            if ($existing === null) {
                DB::table($table)
                    ->where($ownerColumn, $row->{$ownerColumn})
                    ->where('skill_id', $from->id)
                    ->update(['skill_id' => $into->id]);

                continue;
            }

            DB::table($table)
                ->where($ownerColumn, $row->{$ownerColumn})
                ->where('skill_id', $into->id)
                ->update([$levelColumn => $stronger($row->{$levelColumn}, $existing->{$levelColumn})]);

            DB::table($table)
                ->where($ownerColumn, $row->{$ownerColumn})
                ->where('skill_id', $from->id)
                ->delete();
        }
    }
}
