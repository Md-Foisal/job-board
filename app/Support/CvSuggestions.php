<?php

namespace App\Support;

use App\Ai\Agents\CvWriter;
use App\Models\CandidateProfile;
use App\Models\ExperienceRecord;
use Mews\Purifier\Facades\Purifier;

/**
 * What the AI suggested for a CV, after the product has checked it, and
 * what each suggestion was written against.
 *
 * - Lengths follow the profile's own forms: a headline up to 255
 *   characters, a summary up to 1,500, a role's description up to 2,000
 *   characters of stored HTML. A role whose bullets would not fit is kept
 *   but cannot be applied, and says why.
 * - Bullets become a list the product builds itself, every bullet escaped,
 *   then cleaned like any other description, so nothing the model writes
 *   is ever stored as markup.
 * - A suggestion for a role that is not on this profile is dropped.
 * - Any number in a suggestion that the candidate's own text does not
 *   contain is listed, and that suggestion starts unticked: invented and
 *   misplaced figures are the known failure of AI resume writers.
 * - Each suggestion remembers what it was written against -- the headline
 *   and summary text, each role's last change -- so one the candidate has
 *   since edited by hand is closed instead of written over.
 *
 * Converts to and from plain arrays, since it waits in the cache.
 */
final readonly class CvSuggestions
{
    public const HEADLINE_MAX = 255;

    public const SUMMARY_MAX = 1500;

    public const DESCRIPTION_MAX = 2000;

    private const BULLET_MAX = 200;

    private const MAX_BULLETS = 6;

    private const TIP_MAX = 200;

    private const MAX_TIPS = 5;

    /**
     * @param  ?array{suggested: string, based_on: ?string, new_numbers: array<int, string>}  $headline
     * @param  ?array{suggested: string, based_on: ?string, new_numbers: array<int, string>}  $summary
     * @param  array<int, array{id: int, title: string, company: string, bullets: array<int, string>, html: string, based_on: string, new_numbers: array<int, string>, too_long: bool}>  $experience
     * @param  array<int, string>  $tips
     */
    public function __construct(
        public ?array $headline,
        public ?array $summary,
        public array $experience,
        public array $tips,
        public int $version = CvWriter::VERSION,
    ) {}

    /**
     * @param  array<string, mixed>  $answer
     */
    public static function fromAiAnswer(array $answer, CandidateProfile $profile): self
    {
        $records = $profile->experienceRecords()->get()->keyBy('id');
        $everything = self::profileText($profile, $records->all());

        $headline = self::line($answer['headline'] ?? null, self::HEADLINE_MAX);
        $summary = self::text($answer['summary'] ?? null, self::SUMMARY_MAX);

        $experience = collect(is_array($answer['experience'] ?? null) ? $answer['experience'] : [])
            ->filter(fn ($item) => is_array($item) && is_int($item['id'] ?? null) && $records->has($item['id']))
            ->unique('id')
            ->map(function (array $item) use ($records) {
                /** @var ExperienceRecord $record */
                $record = $records->get($item['id']);
                $bullets = self::bullets($item['bullets'] ?? []);

                if ($bullets === []) {
                    return null;
                }

                $html = Purifier::clean('<ul>'.collect($bullets)->map(fn (string $bullet) => '<li>'.e($bullet).'</li>')->implode('').'</ul>', 'richtext');

                return [
                    'id' => $record->id,
                    'title' => $record->job_title,
                    'company' => $record->company_name,
                    'bullets' => $bullets,
                    'html' => $html,
                    'based_on' => $record->updated_at->toJSON(),
                    'new_numbers' => self::newNumbers(implode("\n", $bullets), self::profileText(null, [$record])),
                    'too_long' => mb_strlen($html) > self::DESCRIPTION_MAX,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return new self(
            headline: $headline !== null && $headline !== trim((string) $profile->headline)
                ? ['suggested' => $headline, 'based_on' => $profile->headline, 'new_numbers' => self::newNumbers($headline, $everything)]
                : null,
            summary: $summary !== null && $summary !== trim((string) $profile->bio)
                ? ['suggested' => $summary, 'based_on' => $profile->bio, 'new_numbers' => self::newNumbers($summary, $everything)]
                : null,
            experience: $experience,
            tips: collect(is_array($answer['tips'] ?? null) ? $answer['tips'] : [])
                ->map(fn ($tip) => self::line($tip, self::TIP_MAX))
                ->filter()
                ->take(self::MAX_TIPS)
                ->values()
                ->all(),
        );
    }

    /**
     * These suggestions without the ones already applied.
     *
     * @param  array<int, string|int>  $applied  "headline", "summary" and role ids
     */
    public function without(array $applied): self
    {
        return new self(
            headline: in_array('headline', $applied, true) ? null : $this->headline,
            summary: in_array('summary', $applied, true) ? null : $this->summary,
            experience: array_values(array_filter($this->experience, fn (array $role) => ! in_array($role['id'], $applied, true))),
            tips: $this->tips,
            version: $this->version,
        );
    }

    public function isEmpty(): bool
    {
        return $this->headline === null && $this->summary === null && $this->experience === [] && $this->tips === [];
    }

    /**
     * Whether a suggestion still applies to the profile as it is now.
     */
    public function headlineIsCurrent(CandidateProfile $profile): bool
    {
        return $this->headline !== null && $this->headline['based_on'] === $profile->headline;
    }

    public function summaryIsCurrent(CandidateProfile $profile): bool
    {
        return $this->summary !== null && $this->summary['based_on'] === $profile->bio;
    }

    /**
     * @param  array{based_on: string}  $role
     */
    public static function roleIsCurrent(array $role, ?ExperienceRecord $record): bool
    {
        return $record !== null && $record->updated_at->toJSON() === $role['based_on'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'headline' => $this->headline,
            'summary' => $this->summary,
            'experience' => $this->experience,
            'tips' => $this->tips,
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
            experience: $data['experience'] ?? [],
            tips: $data['tips'] ?? [],
            version: $data['version'] ?? 0,
        );
    }

    /**
     * Every number the suggestion contains that the source does not, as
     * written -- "40%", "2021" and "1,200" each count as one.
     *
     * @return array<int, string>
     */
    private static function newNumbers(string $suggestion, string $source): array
    {
        $numbers = fn (string $text) => preg_match_all('/\d+(?:[.,]\d+)*/u', $text, $matches) ? $matches[0] : [];
        $known = array_map(fn (string $number) => str_replace(',', '', $number), $numbers($source));

        return collect($numbers($suggestion))
            ->reject(fn (string $number) => in_array(str_replace(',', '', $number), $known, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The candidate's own words a suggestion may draw numbers from: for a
     * role, only that role; for the headline and summary, the whole
     * professional profile.
     *
     * @param  array<int, ExperienceRecord>  $records
     */
    private static function profileText(?CandidateProfile $profile, array $records): string
    {
        $parts = $profile === null ? [] : [
            $profile->headline,
            $profile->bio,
            ...$profile->educationRecords()->get()->flatMap(fn ($record) => [
                $record->institution_name, $record->degree, $record->field_of_study,
                $record->start_date?->format('Y'), $record->end_date?->format('Y'),
            ])->all(),
            ...$profile->skills()->pluck('name')->all(),
        ];

        foreach ($records as $record) {
            array_push(
                $parts,
                $record->job_title,
                $record->company_name,
                $record->start_date?->format('Y'),
                $record->end_date?->format('Y'),
                RichText::plain($record->description, PHP_INT_MAX),
            );
        }

        return implode("\n", array_filter($parts, fn ($part) => filled($part)));
    }

    /**
     * @return array<int, string>
     */
    private static function bullets(mixed $bullets): array
    {
        return collect(is_array($bullets) ? $bullets : [])
            ->map(fn ($bullet) => self::line(is_string($bullet) ? ltrim($bullet, " \t•*-–") : null, self::BULLET_MAX))
            ->filter()
            ->take(self::MAX_BULLETS)
            ->values()
            ->all();
    }

    /**
     * One line of plain text, or null when empty or longer than $max.
     */
    private static function line(mixed $value, int $max): ?string
    {
        $text = self::text($value, PHP_INT_MAX);
        $text = $text === null ? null : trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return $text === null || $text === '' || mb_strlen($text) > $max ? null : $text;
    }

    /**
     * Plain text with its line breaks, or null when empty or longer than
     * $max.
     */
    private static function text(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = trim(preg_replace('/[ \t]+/u', ' ', strip_tags($value)) ?? '');

        return $text === '' || mb_strlen($text) > $max ? null : $text;
    }
}
