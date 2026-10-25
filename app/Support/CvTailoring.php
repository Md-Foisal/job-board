<?php

namespace App\Support;

use App\Enums\SkillImportance;
use App\Models\CvVersion;
use App\Models\JobPosting;

/**
 * How well a CV covers the job it is aimed at, by our own reading, with
 * no AI: the job's listed skills, and every skill on the platform's list
 * that the job's description names, compared with what the CV says.
 *
 * A keyword counts as covered when it is among the CV's skills or written
 * anywhere on the CV as a whole word -- what an applicant tracking
 * system's keyword search would find.
 */
final readonly class CvTailoring
{
    /**
     * Coverage below this reads as a CV that needs work for this job.
     */
    public const GOOD_COVERAGE = 70;

    /**
     * @param  list<string>  $keywords
     * @param  list<string>  $required  the job's required skills, a subset of the keywords
     */
    public function __construct(
        public array $keywords,
        public array $required,
        public ?JobPosting $job,
    ) {}

    public static function for(CvVersion $version): self
    {
        $job = $version->jobPosting;
        $names = collect();
        $required = collect();

        if ($job !== null) {
            $job->loadMissing('skills');
            $names = $names->merge($job->skills->pluck('name'));
            $required = $job->skills
                ->filter(fn ($skill) => $skill->pivot->importance === SkillImportance::Required)
                ->pluck('name');
            $names = $names->merge(SkillMatcher::inText(strip_tags((string) $job->description))->pluck('name'));
        }

        if (filled($version->job_description)) {
            $names = $names->merge(SkillMatcher::inText($version->job_description)->pluck('name'));
        }

        return new self(
            keywords: $names->unique(fn (string $name) => mb_strtolower($name))->values()->all(),
            required: $required->values()->all(),
            job: $job,
        );
    }

    public function hasTarget(): bool
    {
        return $this->keywords !== [];
    }

    /**
     * @return array{covered: list<string>, missing: list<string>, percent: ?int}
     */
    public function coverage(CvData $cv): array
    {
        $text = $cv->plainText();
        $skills = array_map('mb_strtolower', $cv->skills);

        [$covered, $missing] = collect($this->keywords)->partition(
            fn (string $keyword) => in_array(mb_strtolower($keyword), $skills, true) || SkillMatcher::mentions($keyword, $text),
        );

        return [
            'covered' => $covered->values()->all(),
            'missing' => $missing->values()->all(),
            'percent' => $this->keywords === [] ? null : (int) round($covered->count() / count($this->keywords) * 100),
        ];
    }
}
