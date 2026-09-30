<?php

namespace App\Actions;

use App\Enums\ProficiencyLevel;
use App\Models\CandidateProfile;
use App\Models\EducationRecord;
use App\Models\ExperienceRecord;
use App\Models\Skill;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Write what the candidate chose from their CV into their profile, all or
 * nothing.
 *
 * The selection has already been validated by the page with the same
 * rules as the profile's own forms; this only decides what is new:
 *
 * - Profile fields (headline, bio, links) are written as given. The page
 *   only passes a filled field when the candidate ticked "replace".
 * - Skills come only from the platform's list, and one the candidate
 *   already has keeps the level they set. New ones start at intermediate,
 *   the same default as adding a skill by hand.
 * - A role is already there when the company, title and start month match
 *   one on the profile; a course, when the institution, degree and start
 *   month do. Those are skipped, so importing the same CV twice adds
 *   nothing the second time.
 */
class ImportResumeToProfile
{
    private const PROFILE_FIELDS = ['headline', 'bio', 'linkedin_url', 'github_url', 'portfolio_url'];

    /**
     * @param  array{
     *     profile?: array<string, string>,
     *     skill_ids?: array<int, int>,
     *     experience?: array<int, array{company_name: string, job_title: string, description: ?string, start_date: string, end_date: ?string}>,
     *     education?: array<int, array{institution_name: string, degree: ?string, field_of_study: ?string, start_date: string, end_date: ?string}>,
     * }  $selection
     * @return array{profile: int, skills: int, experience: int, education: int} how many of each were added
     */
    public function __invoke(CandidateProfile $candidateProfile, array $selection): array
    {
        return DB::transaction(function () use ($candidateProfile, $selection) {
            $profile = array_intersect_key($selection['profile'] ?? [], array_flip(self::PROFILE_FIELDS));
            $candidateProfile->update($profile);

            return [
                'profile' => count($profile),
                'skills' => $this->addSkills($candidateProfile, $selection['skill_ids'] ?? []),
                'experience' => $this->addExperience($candidateProfile, $selection['experience'] ?? []),
                'education' => $this->addEducation($candidateProfile, $selection['education'] ?? []),
            ];
        });
    }

    /**
     * @param  array<int, int>  $skillIds
     */
    private function addSkills(CandidateProfile $candidateProfile, array $skillIds): int
    {
        $new = Skill::query()
            ->whereIn('id', $skillIds)
            ->whereNotIn('id', $candidateProfile->skills()->select('skills.id'))
            ->pluck('id');

        $candidateProfile->skills()->attach(
            $new->mapWithKeys(fn (int $id) => [$id => ['proficiency' => ProficiencyLevel::Intermediate->value]])->all(),
        );

        return $new->count();
    }

    /**
     * @param  array<int, array<string, ?string>>  $entries
     */
    private function addExperience(CandidateProfile $candidateProfile, array $entries): int
    {
        $existing = $candidateProfile->experienceRecords()->get()
            ->map(fn (ExperienceRecord $record) => self::key($record->company_name, $record->job_title, $record->start_date))
            ->all();

        $added = 0;

        foreach ($entries as $entry) {
            $key = self::key($entry['company_name'], $entry['job_title'], CarbonImmutable::parse($entry['start_date']));

            if (in_array($key, $existing, true)) {
                continue;
            }

            $candidateProfile->experienceRecords()->create($entry);
            $existing[] = $key;
            $added++;
        }

        return $added;
    }

    /**
     * @param  array<int, array<string, ?string>>  $entries
     */
    private function addEducation(CandidateProfile $candidateProfile, array $entries): int
    {
        $existing = $candidateProfile->educationRecords()->get()
            ->map(fn (EducationRecord $record) => self::key($record->institution_name, (string) $record->degree, $record->start_date))
            ->all();

        $added = 0;

        foreach ($entries as $entry) {
            $key = self::key($entry['institution_name'], (string) ($entry['degree'] ?? ''), CarbonImmutable::parse($entry['start_date']));

            if (in_array($key, $existing, true)) {
                continue;
            }

            $candidateProfile->educationRecords()->create($entry);
            $existing[] = $key;
            $added++;
        }

        return $added;
    }

    /**
     * Two entries are the same when their names match, ignoring case and
     * spacing, and they started in the same month.
     */
    private static function key(string $first, string $second, DateTimeInterface $start): string
    {
        $normalise = fn (string $value) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? ''));

        return $normalise($first).'|'.$normalise($second).'|'.$start->format('Y-m');
    }
}
