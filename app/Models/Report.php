<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['reporter_id', 'reason', 'review_status'])]
class Report extends Model
{
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
