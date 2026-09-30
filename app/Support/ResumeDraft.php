<?php

namespace App\Support;

use App\Models\Skill;

/**
 * What reading a CV suggested for the profile, before the candidate has
 * chosen any of it. Nothing here is saved on its own; the candidate ticks
 * what they want and ImportResumeToProfile writes only that.
 *
 * The product's own reading fills the skills and links. Headline, summary,
 * experience and education come only from the AI reading, which fills the
 * same shape, so the page and the import handle both the same way.
 *
 * Dates are months, "2021-03", because that is what a CV gives; a missing
 * start date stays null and the page asks for it before the entry can be
 * added. Everything converts to and from plain arrays, since the AI
 * reading's draft waits in the cache.
 */
final class ResumeDraft
{
    /**
     * @param  array{linkedin_url: ?string, github_url: ?string, portfolio_url: ?string}  $links
     * @param  array<int, array{id: int, name: string}>  $skills
     * @param  array<int, string>  $unmatchedSkills
     * @param  array<int, array{company_name: string, job_title: string, description: ?string, start_month: ?string, end_month: ?string}>  $experience
     * @param  array<int, array{institution_name: string, degree: ?string, field_of_study: ?string, start_month: ?string, end_month: ?string}>  $education
     */
    public function __construct(
        public readonly ?string $headline = null,
        public readonly ?string $summary = null,
        public readonly array $links = ['linkedin_url' => null, 'github_url' => null, 'portfolio_url' => null],
        public readonly array $skills = [],
        public readonly array $unmatchedSkills = [],
        public readonly array $experience = [],
        public readonly array $education = [],
    ) {}

    /**
     * The product's own reading: skills on the platform's list and profile
     * links, found in the CV's text.
     */
    public static function fromText(string $text): self
    {
        return new self(
            links: CvLinks::from($text),
            skills: SkillMatcher::inText($text)
                ->map(fn (Skill $skill) => ['id' => $skill->id, 'name' => $skill->name])
                ->all(),
        );
    }

    public function isEmpty(): bool
    {
        return blank($this->headline)
            && blank($this->summary)
            && array_filter($this->links) === []
            && $this->skills === []
            && $this->unmatchedSkills === []
            && $this->experience === []
            && $this->education === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'headline' => $this->headline,
            'summary' => $this->summary,
            'links' => $this->links,
            'skills' => $this->skills,
            'unmatched_skills' => $this->unmatchedSkills,
            'experience' => $this->experience,
            'education' => $this->education,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            headline: $data['headline'] ?? null,
            summary: $data['summary'] ?? null,
            links: ($data['links'] ?? []) + ['linkedin_url' => null, 'github_url' => null, 'portfolio_url' => null],
            skills: $data['skills'] ?? [],
            unmatchedSkills: $data['unmatched_skills'] ?? [],
            experience: $data['experience'] ?? [],
            education: $data['education'] ?? [],
        );
    }
}
