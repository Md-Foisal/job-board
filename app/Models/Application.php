<?php

namespace App\Models;

use App\Casts\SanitizedHtml;
use App\Enums\ApplicationOutcomeStatus;
use App\Enums\ApplicationStage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'job_posting_id', 'candidate_profile_id', 'resume_document_id',
    'cover_letter', 'outcome_status', 'stage',
])]
class Application extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'cover_letter' => SanitizedHtml::class,
            'outcome_status' => ApplicationOutcomeStatus::class,
            'stage' => ApplicationStage::class,
        ];
    }

    public function jobPosting()
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function candidateProfile()
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    /**
     * The CV exactly as it was sent. Including removed ones is the point:
     * a candidate taking a CV out of their library (or an upload pushing
     * an old one out) must not take it away from an employer who already
     * received it -- the application is a snapshot (claude/13, question 4).
     */
    public function resumeDocument()
    {
        return $this->belongsTo(Document::class, 'resume_document_id')->withTrashed();
    }

    public function screeningAnswers()
    {
        return $this->hasMany(ScreeningAnswer::class);
    }

    public function events()
    {
        return $this->hasMany(ApplicationEvent::class)->latest('created_at')->latest('id');
    }

    /**
     * Private hiring-side commentary. Newest first: a reviewer opening an
     * application wants the most recent read on the candidate, not the
     * first one written weeks ago.
     */
    public function notes()
    {
        return $this->hasMany(ApplicationNote::class)->latest()->latest('id');
    }
}
