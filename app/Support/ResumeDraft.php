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
     * The same limits as the profile's own forms.
     */
    private const HEADLINE_MAX = 255;

    private const SUMMARY_MAX = 5000;

    private const NAME_MAX = 255;

    private const DESCRIPTION_MAX = 2000;

    /**
     * More roles or courses than any real CV lists; the rest are dropped.
     */
    private const MAX_ENTRIES = 30;

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

    /**
     * The AI reading's answer, checked the way a form would check it: text
     * trimmed and cut to what the profile's own fields hold, months that
     * are not YYYY-MM dropped, links kept only if they pass the same rules
     * as the product's own link reading, and skills matched to the
     * platform's list, with the rest reported as not on it.
     *
     * @param  array<string, mixed>  $answer
     */
    public static function fromAiAnswer(array $answer): self
    {
        $skills = SkillMatcher::byNames(array_filter((array) ($answer['skills'] ?? []), 'is_string'));

        return new self(
            headline: self::text($answer['headline'] ?? null, self::HEADLINE_MAX),
            summary: self::text($answer['summary'] ?? null, self::SUMMARY_MAX),
            links: CvLinks::from(implode("\n", array_filter((array) ($answer['links'] ?? []), 'is_string'))),
            skills: $skills['matched']->map(fn (Skill $skill) => ['id' => $skill->id, 'name' => $skill->name])->all(),
            unmatchedSkills: array_slice($skills['unmatched'], 0, self::MAX_ENTRIES),
            experience: self::entries($answer['experience'] ?? [], fn (array $entry) => [
                'company_name' => self::text($entry['company_name'] ?? null, self::NAME_MAX),
                'job_title' => self::text($entry['job_title'] ?? null, self::NAME_MAX),
                'description' => self::text($entry['description'] ?? null, self::DESCRIPTION_MAX),
                'start_month' => self::month($entry['start_month'] ?? null),
                'end_month' => self::month($entry['end_month'] ?? null),
            ], ['company_name', 'job_title']),
            education: self::entries($answer['education'] ?? [], fn (array $entry) => [
                'institution_name' => self::text($entry['institution_name'] ?? null, self::NAME_MAX),
                'degree' => self::text($entry['degree'] ?? null, self::NAME_MAX),
                'field_of_study' => self::text($entry['field_of_study'] ?? null, self::NAME_MAX),
                'start_month' => self::month($entry['start_month'] ?? null),
                'end_month' => self::month($entry['end_month'] ?? null),
            ], ['institution_name']),
        );
    }

    /**
     * This draft with the AI reading's added to it. Links the product
     * found itself are kept over the AI's, skills are combined, and the
     * fields only the AI fills come from the AI.
     */
    public function merge(self $ai): self
    {
        $links = $this->links;

        foreach ($ai->links as $field => $link) {
            $links[$field] ??= $link;
        }

        return new self(
            headline: $ai->headline,
            summary: $ai->summary,
            links: $links,
            skills: array_values(array_column([...$this->skills, ...$ai->skills], null, 'id')),
            unmatchedSkills: $ai->unmatchedSkills,
            experience: $ai->experience,
            education: $ai->education,
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

    private static function text(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(preg_replace('/[ \t]+/u', ' ', strip_tags($value)) ?? '');

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private static function month(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^(19|20)\d{2}-(0[1-9]|1[0-2])$/', $value) ? $value : null;
    }

    /**
     * @param  callable(array<string, mixed>): array<string, ?string>  $clean
     * @param  array<int, string>  $required
     * @return array<int, array<string, ?string>>
     */
    private static function entries(mixed $entries, callable $clean, array $required): array
    {
        return collect(is_array($entries) ? $entries : [])
            ->filter(fn ($entry) => is_array($entry))
            ->map($clean)
            ->filter(fn (array $entry) => collect($required)->every(fn (string $field) => $entry[$field] !== null))
            ->take(self::MAX_ENTRIES)
            ->values()
            ->all();
    }
}
