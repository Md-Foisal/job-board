<?php

namespace App\Support;

use App\Ai\Agents\ReviewScreener;
use App\Enums\ReviewPart;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * The AI's hint on a review or a company's answer, after the product has
 * checked it: the grounds it raised, each with a short note for staff.
 *
 * Only grounds on the part's closed list survive, once each. An answer
 * that is not the expected shape, or whose every concern named a ground
 * that does not exist, is no hint at all rather than an all-clear: the
 * model said something was wrong, and "nothing raised" would tell staff
 * the opposite.
 *
 * Kept on the review row as a plain array, and cleared whenever the text
 * it was about changes.
 */
final readonly class ReviewScreening
{
    /**
     * @param  list<array{reason: string, note: string}>  $concerns
     */
    public function __construct(
        public ReviewPart $part,
        public array $concerns,
        public CarbonImmutable $screenedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $answer
     */
    public static function fromAiAnswer(array $answer, ReviewPart $part): ?self
    {
        if (! is_array($answer['concerns'] ?? null)) {
            return null;
        }

        $grounds = $part->screeningReasons();

        $concerns = collect($answer['concerns'])
            ->map(function (mixed $concern) use ($grounds) {
                if (! is_array($concern) || ! is_string($concern['reason'] ?? null)) {
                    return null;
                }

                // Structured output does not guarantee an enum value's case.
                $reason = Str::lower(trim($concern['reason']));

                if (! array_key_exists($reason, $grounds)) {
                    return null;
                }

                $note = is_string($concern['note'] ?? null) ? Str::squish(strip_tags($concern['note'])) : '';

                return ['reason' => $reason, 'note' => Str::limit($note, ReviewScreener::NOTE_MAX - 1, '…')];
            })
            ->filter()
            ->unique('reason')
            ->values()
            ->all();

        if ($concerns === [] && $answer['concerns'] !== []) {
            return null;
        }

        return new self($part, $concerns, CarbonImmutable::now());
    }

    /**
     * @param  array{concerns: list<array{reason: string, note: string}>, screened_at: string}|null  $stored
     */
    public static function fromStored(?array $stored, ReviewPart $part): ?self
    {
        if ($stored === null) {
            return null;
        }

        return new self($part, $stored['concerns'] ?? [], CarbonImmutable::parse($stored['screened_at']));
    }

    /**
     * @return array{concerns: list<array{reason: string, note: string}>, screened_at: string}
     */
    public function toArray(): array
    {
        return [
            'concerns' => $this->concerns,
            'screened_at' => $this->screenedAt->toIso8601String(),
        ];
    }

    public function raisedNothing(): bool
    {
        return $this->concerns === [];
    }

    /**
     * Each concern as one line for staff: the ground, then the note.
     *
     * @return list<string>
     */
    public function lines(): array
    {
        return array_map(
            fn (array $concern) => $concern['note'] === ''
                ? $this->label($concern['reason'])
                : $this->label($concern['reason']).': '.$concern['note'],
            $this->concerns,
        );
    }

    /**
     * @return list<string>
     */
    public function labels(): array
    {
        return array_map(fn (array $concern) => $this->label($concern['reason']), $this->concerns);
    }

    private function label(string $reason): string
    {
        return $this->part->screeningReasons()[$reason] ?? $reason;
    }
}
