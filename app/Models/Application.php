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
            'decided_at' => 'datetime',
        ];
    }

    /**
     * How long a hire or rejection can still be taken back. The candidate
     * is told only once it has passed, so a slip of the hand never reaches
     * a real person as final news.
     */
    public const UNDO_MINUTES = 10;

    /**
     * Whether the company's decision is still inside its undo window.
     */
    public function decisionIsUndoable(): bool
    {
        return in_array($this->outcome_status, [ApplicationOutcomeStatus::Hired, ApplicationOutcomeStatus::Rejected], true)
            && $this->decided_at !== null
            && $this->decided_at->gt(now()->subMinutes(self::UNDO_MINUTES));
    }

    /**
     * The outcome as the candidate may see it: a decision still inside its
     * undo window has not been made yet, as far as they are concerned.
     */
    public function outcomeForCandidate(): ApplicationOutcomeStatus
    {
        return $this->decisionIsUndoable() ? ApplicationOutcomeStatus::Active : $this->outcome_status;
    }

    /**
     * The history the candidate is shown: a decision that was taken back,
     * the taking back itself, and a decision still inside its undo window
     * are left out. Keeps the order the events were loaded in.
     */
    public function eventsForCandidate()
    {
        $hidden = [];
        $undoPending = false;
        $newestFirst = $this->events->sortByDesc(fn ($event) => [$event->created_at->getTimestamp(), $event->id]);

        foreach ($newestFirst as $event) {
            if ($event->to_outcome_status === ApplicationOutcomeStatus::Active->value) {
                $hidden[] = $event->id;
                $undoPending = true;

                continue;
            }

            if (in_array($event->to_outcome_status, [ApplicationOutcomeStatus::Hired->value, ApplicationOutcomeStatus::Rejected->value], true)
                && $undoPending) {
                $hidden[] = $event->id;
                $undoPending = false;
            }
        }

        if ($this->decisionIsUndoable()) {
            $latestDecision = $newestFirst->first(fn ($event) => $event->to_outcome_status === $this->outcome_status->value);

            if ($latestDecision !== null) {
                $hidden[] = $latestDecision->id;
            }
        }

        return $this->events->reject(fn ($event) => in_array($event->id, $hidden, true))->values();
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
     * The candidate's review of the hiring process behind this
     * application -- at most one.
     */
    public function review()
    {
        return $this->hasOne(CompanyReview::class);
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
