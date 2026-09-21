<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['reporter_id', 'reason', 'review_status'])]
class Report extends Model
{
    /**
     * How many different people must have an open report on something
     * before it leaves public view. Counted per person, so one account
     * reporting the same thing again cannot take it down alone.
     */
    public const HIDE_AFTER_REPORTERS = 3;

    /**
     * Ids of the subjects of one type that are out of public view while
     * their open reports wait for staff.
     */
    public static function subjectsHiddenPendingReview(string $morphClass): Builder
    {
        return static::query()
            ->select('reportable_id')
            ->where('reportable_type', $morphClass)
            ->where('review_status', ReportStatus::Pending)
            ->groupBy('reportable_id')
            ->havingRaw('count(distinct reporter_id) >= ?', [self::HIDE_AFTER_REPORTERS]);
    }

    protected function casts(): array
    {
        return [
            'review_status' => ReportStatus::class,
        ];
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reportable()
    {
        return $this->morphTo();
    }

    /**
     * The company a report ultimately concerns -- itself if the subject
     * is a company, its owner if the subject is a job posting. Staff use
     * this to recuse themselves from reports about their own employer.
     */
    public function subjectCompany(): ?Company
    {
        $subject = $this->reportable;

        return match (true) {
            $subject instanceof Company => $subject,
            $subject instanceof JobPosting => $subject->company,
            default => null,
        };
    }
}
