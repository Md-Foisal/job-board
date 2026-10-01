<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Every number on the employer's analytics page for one company, or one
 * of its postings, over one range of days.
 *
 * A null means "not enough to say", never zero: a rate over a handful of
 * views or a median of two values would read as a finding when it is
 * noise, so the page shows "not enough data yet" instead.
 */
final readonly class JobPerformanceReport
{
    /**
     * @param  array<string, int>  $dailyViews  date => views, every day of the range
     * @param  array<string, int>  $dailyApplications  date => applications, every day of the range
     * @param  array{shortlisted: int, interview: int, offer: int, hired: int}  $funnel
     * @param  ?array{low: int, medium: int, high: int}  $skillMatch
     * @param  array<int, int>  $timeToFillDays  job posting id => days from publishing to the first hire
     * @param  ?array{status: string, published_at: ?CarbonImmutable, live_days: ?int, expires_in_days: ?int}  $job
     */
    public function __construct(
        public int $days,
        public CarbonImmutable $from,
        public int $views,
        public ?CarbonImmutable $viewsCountedSince,
        public array $dailyViews,
        public int $applications,
        public array $dailyApplications,
        public ?float $applyRate,
        public array $funnel,
        public int $rejectedUnseen,
        public ?array $skillMatch,
        public int $responded,
        public ?float $firstResponseMedianHours,
        public int $waiting,
        public ?CarbonImmutable $oldestWaitingSince,
        public int $hires,
        public ?float $timeToHireMedianDays,
        public array $timeToFillDays,
        public int $saves,
        public ?array $job,
    ) {}

    /**
     * Plain values only: the cache refuses to rebuild objects it reads
     * back, so the report is stored as an array and rebuilt from it.
     */
    public function toArray(): array
    {
        $date = fn (?CarbonImmutable $at) => $at?->toIso8601String();

        return [
            ...get_object_vars($this),
            'from' => $date($this->from),
            'viewsCountedSince' => $date($this->viewsCountedSince),
            'oldestWaitingSince' => $date($this->oldestWaitingSince),
            'job' => $this->job !== null ? [...$this->job, 'published_at' => $date($this->job['published_at'])] : null,
        ];
    }

    public static function fromArray(array $data): self
    {
        $date = fn (?string $at) => $at !== null ? CarbonImmutable::parse($at) : null;

        return new self(...[
            ...$data,
            'from' => $date($data['from']),
            'viewsCountedSince' => $date($data['viewsCountedSince']),
            'oldestWaitingSince' => $date($data['oldestWaitingSince']),
            'job' => $data['job'] !== null ? [...$data['job'], 'published_at' => $date($data['job']['published_at'])] : null,
        ]);
    }

    /**
     * Whether views were already being counted on the first day of the
     * range. When they were not, the range starts before counting did and
     * the page says so, rather than letting the empty days look like
     * nobody came.
     */
    public function viewsCoverRange(): bool
    {
        return $this->viewsCountedSince !== null && $this->viewsCountedSince->lte($this->from);
    }
}
