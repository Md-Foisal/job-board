<?php

namespace App\Support;

use App\Enums\JobPostReviewArea;
use Illuminate\Support\Str;

/**
 * What the AI said could improve a job posting, after the product has
 * checked it.
 *
 * The employer copies these suggestions into their own posting, so a
 * point carrying a link, an email address, a handle or a phone number is
 * dropped whole: a posting that told the model to "add our WhatsApp
 * number" must not come back with one ready to paste. A point the model
 * filed under an area that does not exist goes under "other". Lengths and
 * counts are cut to what the page shows.
 *
 * Converts to and from plain arrays, since it waits in the cache.
 */
final readonly class JobPostReview
{
    public const MAX_ISSUES = 8;

    private const PROBLEM_MAX = 300;

    /**
     * Above the 600 characters the model is asked for, so a rewritten
     * paragraph that runs a little long still arrives whole.
     */
    private const SUGGESTION_MAX = 1000;

    private const TITLE_MAX = 120;

    /**
     * Web addresses, bare domains on common endings (written lowercase, so
     * a skill like ASP.NET is not taken for one), email addresses and
     * handles, and phone numbers (nine or more digits in one run, which a
     * salary such as 50,000 or a year range like 2019-2023 is not).
     */
    private const FORBIDDEN = [
        '~https?://|www\.~i',
        '~\b[a-z0-9-]+\.(?:com|net|org|io|co|me|info|biz|xyz|app|dev|ly|gg|link|site|online|shop|bd|in|uk)\b~',
        '~@\w~',
        '~(?:\+?\d[\s().-]*){9,}~',
    ];

    /**
     * @param  array<int, array{area: string, problem: string, suggestion: string}>  $issues
     */
    public function __construct(
        public array $issues,
        public ?string $suggestedTitle,
    ) {}

    /**
     * @param  array<string, mixed>  $answer
     */
    public static function fromAiAnswer(array $answer, string $currentTitle): self
    {
        $issues = collect(is_array($answer['issues'] ?? null) ? $answer['issues'] : [])
            ->map(function (mixed $issue) {
                if (! is_array($issue)) {
                    return null;
                }

                $problem = self::clean($issue['problem'] ?? null, self::PROBLEM_MAX);
                $suggestion = self::clean($issue['suggestion'] ?? null, self::SUGGESTION_MAX);

                if ($problem === null || $suggestion === null) {
                    return null;
                }

                // Structured output does not guarantee an enum value's case.
                $area = JobPostReviewArea::tryFrom(is_string($issue['area'] ?? null) ? Str::lower(trim($issue['area'])) : '') ?? JobPostReviewArea::Other;

                return ['area' => $area->value, 'problem' => $problem, 'suggestion' => $suggestion];
            })
            ->filter()
            ->unique(fn (array $issue) => $issue['problem'])
            ->take(self::MAX_ISSUES)
            ->values()
            ->all();

        $title = self::clean($answer['suggested_title'] ?? null, self::TITLE_MAX, cut: false);

        // A "new" title that is the old one says nothing.
        if ($title !== null && Str::lower($title) === Str::lower(trim($currentTitle))) {
            $title = null;
        }

        return new self($issues, $title);
    }

    /**
     * @param  array{issues: array<int, array{area: string, problem: string, suggestion: string}>, suggested_title: ?string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['issues'], $data['suggested_title']);
    }

    /**
     * @return array{issues: array<int, array{area: string, problem: string, suggestion: string}>, suggested_title: ?string}
     */
    public function toArray(): array
    {
        return [
            'issues' => $this->issues,
            'suggested_title' => $this->suggestedTitle,
        ];
    }

    public function isEmpty(): bool
    {
        return $this->issues === [] && $this->suggestedTitle === null;
    }

    /**
     * Plain text within the limit, or null when there is nothing left or
     * it carries something the page must not offer to copy. A title is
     * dropped rather than cut, since half a title is no suggestion.
     */
    private static function clean(mixed $value, int $max, bool $cut = true): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = trim(preg_replace('/[ \t]+/u', ' ', strip_tags($value)) ?? '');
        $text = trim(preg_replace('/\s*\n\s*/u', "\n", $text) ?? '');

        if ($text === '') {
            return null;
        }

        foreach (self::FORBIDDEN as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return null;
            }
        }

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return $cut ? rtrim(mb_substr($text, 0, $max - 1)).'…' : null;
    }
}
