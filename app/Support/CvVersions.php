<?php

namespace App\Support;

use App\Models\CandidateProfile;
use App\Models\CvVersion;
use App\Models\JobPosting;

/**
 * Opening, copying and aiming a candidate's saved CVs. A candidate who
 * has none yet gets "My CV" the first time the builder opens, so there
 * is always one to work on.
 */
final class CvVersions
{
    /**
     * The CV asked for, if it is the candidate's; otherwise the one worked
     * on most recently; otherwise a new one.
     */
    public static function open(CandidateProfile $profile, mixed $id = null): CvVersion
    {
        $versions = $profile->cvVersions();

        if (is_numeric($id) && $version = (clone $versions)->find((int) $id)) {
            return $version;
        }

        return (clone $versions)->latest('updated_at')->latest('id')->first()
            ?? $profile->cvVersions()->create(['name' => __('My CV'), 'design' => self::startingDesign()]);
    }

    /**
     * A copy of a CV, everything but its web page: a copy is a new CV,
     * and an address someone already has keeps pointing at the original.
     */
    public static function copy(CvVersion $version, ?string $name = null): CvVersion
    {
        return $version->candidateProfile->cvVersions()->create([
            ...$version->only(['design', 'extras', 'hidden_entries', 'headline', 'summary', 'job_posting_id', 'job_description']),
            'name' => $name ?? mb_substr(__('Copy of :name', ['name' => $version->name]), 0, 80),
        ]);
    }

    /**
     * The CV aimed at one job: the one made for it before, or a copy of
     * the CV worked on most recently, named after the job.
     */
    public static function forJob(CandidateProfile $profile, JobPosting $job): ?CvVersion
    {
        if ($existing = $profile->cvVersions()->where('job_posting_id', $job->id)->latest('id')->first()) {
            return $existing;
        }

        if ($profile->cvVersions()->count() >= CvVersion::MAX_PER_PROFILE) {
            return null;
        }

        $base = self::open($profile);
        $name = mb_substr(__(':title at :company', ['title' => $job->title, 'company' => $job->company->name]), 0, 80);

        $version = self::copy($base, $name);
        $version->update(['job_posting_id' => $job->id, 'job_description' => null]);

        return $version;
    }

    /**
     * @return array<string, mixed>
     */
    public static function startingDesign(): array
    {
        return [...(new CvDesign)->toArray(), 'photo' => false, 'paper' => 'a4'];
    }
}
