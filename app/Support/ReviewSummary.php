<?php

namespace App\Support;

use App\Enums\JobAsDescribed;
use App\Models\Company;
use App\Models\CompanyReview;

/**
 * The figures above a company's reviews: how many there are, and once
 * there are enough, the average ratings and how many said the job was as
 * advertised.
 *
 * Below three published reviews only the count exists. An average of one
 * review is that one person's rating under a different name, and two
 * hardly fewer, so the figures are not just hidden there but never built.
 */
final readonly class ReviewSummary
{
    public const MIN_FOR_AVERAGES = 3;

    private function __construct(
        public int $count,
        public ?float $overall,
        public ?float $communication,
        public ?int $asDescribed,
    ) {}

    public static function of(Company $company): self
    {
        $row = CompanyReview::query()
            ->where('company_id', $company->id)
            ->published()
            ->toBase()
            ->selectRaw('count(*) as reviews')
            ->selectRaw('avg(overall_rating) as overall')
            ->selectRaw('avg(communication_rating) as communication')
            ->selectRaw('sum(case when job_as_described = ? then 1 else 0 end) as as_described', [JobAsDescribed::Yes->value])
            ->first();

        $count = (int) $row->reviews;

        if ($count < self::MIN_FOR_AVERAGES) {
            return new self($count, null, null, null);
        }

        return new self(
            $count,
            round((float) $row->overall, 1),
            round((float) $row->communication, 1),
            (int) $row->as_described,
        );
    }

    public function hasAverages(): bool
    {
        return $this->overall !== null;
    }
}
