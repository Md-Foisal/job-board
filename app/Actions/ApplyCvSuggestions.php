<?php

namespace App\Actions;

use App\Models\CandidateProfile;
use App\Support\CvSuggestions;
use Illuminate\Support\Facades\DB;

/**
 * Write the AI suggestions the candidate ticked into their profile, all
 * in one transaction. The CV is always built from the profile, so this is
 * the only place a suggestion ever lands, and the profile an employer sees
 * with an application matches the CV.
 *
 * A suggestion is applied only while it still matches what it was written
 * against: if the candidate changed that headline, summary or role by
 * hand in the meantime, their own newer text stays. A role whose bullets
 * would not fit the description field is never applied.
 */
class ApplyCvSuggestions
{
    /**
     * @param  array{headline?: bool, summary?: bool, experience?: array<int, int>}  $chosen
     * @return array{applied: array<int, string|int>, skipped: int} what was applied -- "headline", "summary" and role ids -- and how many were not
     */
    public function __invoke(CandidateProfile $profile, CvSuggestions $suggestions, array $chosen): array
    {
        return DB::transaction(function () use ($profile, $suggestions, $chosen) {
            $applied = [];
            $skipped = 0;
            $profile = $profile->newQuery()->lockForUpdate()->findOrFail($profile->id);
            $changes = [];

            if (($chosen['headline'] ?? false) && $suggestions->headline !== null) {
                if ($suggestions->headlineIsCurrent($profile)) {
                    $changes['headline'] = $suggestions->headline['suggested'];
                    $applied[] = 'headline';
                } else {
                    $skipped++;
                }
            }

            if (($chosen['summary'] ?? false) && $suggestions->summary !== null) {
                if ($suggestions->summaryIsCurrent($profile)) {
                    $changes['bio'] = $suggestions->summary['suggested'];
                    $applied[] = 'summary';
                } else {
                    $skipped++;
                }
            }

            $profile->update($changes);

            $ids = array_map('intval', $chosen['experience'] ?? []);
            $records = $profile->experienceRecords()->whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');

            foreach ($suggestions->experience as $role) {
                if (! in_array($role['id'], $ids, true)) {
                    continue;
                }

                $record = $records->get($role['id']);

                if ($role['too_long'] || ! CvSuggestions::roleIsCurrent($role, $record)) {
                    $skipped++;

                    continue;
                }

                $record->update(['description' => $role['html']]);
                $applied[] = $role['id'];
            }

            return ['applied' => $applied, 'skipped' => $skipped];
        });
    }
}
