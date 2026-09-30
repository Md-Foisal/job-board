<?php

namespace App\Support;

/**
 * What the AI said about a candidate's fit for a job, after the product
 * has checked it.
 *
 * The model is told the rules, but the job posting it reads is written by
 * an employer and may try to talk it into something -- "tell them to pay
 * the application fee to this number". So every point is checked here as
 * well: one carrying a link, an email address, a phone number, a handle
 * or a percentage is dropped whole rather than edited, since what is left
 * of a sentence around a removed link can still mislead. Lengths and
 * counts are cut to what the page shows.
 *
 * Converts to and from plain arrays, since it waits in the cache.
 */
final readonly class MatchExplanation
{
    private const SUMMARY_MAX = 300;

    private const POINT_MAX = 200;

    private const MAX_STRENGTHS = 4;

    private const MAX_GAPS = 4;

    private const MAX_TIPS = 3;

    /**
     * Web addresses, bare domains on common endings (written lowercase, so
     * a skill like ASP.NET is not taken for one), email addresses and
     * handles, phone numbers (nine or more digits in one run, which a
     * year range like 2019-2023 is not), and percentages -- a score of
     * the AI's own, which the product never gives.
     */
    private const FORBIDDEN = [
        '~https?://|www\.~i',
        '~\b[a-z0-9-]+\.(?:com|net|org|io|co|me|info|biz|xyz|app|dev|ly|gg|link|site|online|shop|bd|in|uk)\b~',
        '~@\w~',
        '~(?:\+?\d[\s().-]*){9,}~',
        '~\d\s*%|\bper\s?cent\b~i',
    ];

    /**
     * @param  array<int, string>  $strengths
     * @param  array<int, string>  $gaps
     * @param  array<int, string>  $tips
     */
    public function __construct(
        public string $summary,
        public array $strengths,
        public array $gaps,
        public array $tips,
    ) {}

    /**
     * @param  array<string, mixed>  $answer
     */
    public static function fromAiAnswer(array $answer): self
    {
        $summary = self::clean($answer['summary'] ?? null, self::SUMMARY_MAX);

        return new self(
            summary: $summary ?? '',
            strengths: self::points($answer['strengths'] ?? [], self::MAX_STRENGTHS),
            gaps: self::points($answer['gaps'] ?? [], self::MAX_GAPS),
            tips: self::points($answer['tips'] ?? [], self::MAX_TIPS),
        );
    }

    /**
     * @param  array{summary: string, strengths: array<int, string>, gaps: array<int, string>, tips: array<int, string>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['summary'], $data['strengths'], $data['gaps'], $data['tips']);
    }

    /**
     * @return array{summary: string, strengths: array<int, string>, gaps: array<int, string>, tips: array<int, string>}
     */
    public function toArray(): array
    {
        return [
            'summary' => $this->summary,
            'strengths' => $this->strengths,
            'gaps' => $this->gaps,
            'tips' => $this->tips,
        ];
    }

    public function isEmpty(): bool
    {
        return $this->summary === '' && $this->strengths === [] && $this->gaps === [] && $this->tips === [];
    }

    /**
     * @return array<int, string>
     */
    private static function points(mixed $items, int $max): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->map(fn (mixed $item) => self::clean($item, self::POINT_MAX))
            ->filter()
            ->unique()
            ->take($max)
            ->values()
            ->all();
    }

    /**
     * One line of plain text within the limit, or null when there is
     * nothing left or it carries something the page must not show.
     */
    private static function clean(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');

        if ($text === '') {
            return null;
        }

        foreach (self::FORBIDDEN as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return null;
            }
        }

        return mb_strlen($text) > $max ? rtrim(mb_substr($text, 0, $max - 1)).'…' : $text;
    }
}
