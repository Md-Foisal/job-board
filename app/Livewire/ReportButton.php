<?php

namespace App\Livewire;

use App\Enums\ModerationStatus;
use App\Enums\ReportStatus;
use App\Models\JobPosting;
use App\Models\Report;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

/**
 * Isolated "report this" action, embedded on job detail (and, once that
 * page exists, company profile) -- a small modal form rather than a
 * dedicated page, per the established page inventory (Report has no
 * page of its own).
 */
class ReportButton extends Component
{
    public Model $reportable;

    public string $reason = '';

    public bool $submitted = false;

    protected array $reasons = [
        'spam' => 'Spam or fake listing',
        'scam' => 'Scam or fraud',
        'inappropriate' => 'Inappropriate content',
        'other' => 'Other',
    ];

    public function mount(Model $reportable): void
    {
        $this->reportable = $reportable;
    }

    public function getReasonsProperty(): array
    {
        return $this->reasons;
    }

    public function submit(): void
    {
        $this->validate([
            'reason' => 'required|string|in:'.implode(',', array_keys($this->reasons)),
        ]);

        Report::create([
            'reporter_id' => auth()->id(),
            'reportable_type' => $this->reportable::class,
            'reportable_id' => $this->reportable->id,
            'reason' => $this->reasons[$this->reason],
            'review_status' => ReportStatus::Pending,
        ]);

        // Three or more independent pending reports on the same subject
        // auto-hide it (not delete) until a moderator reviews it -- an
        // established invariant. Re-using moderation_status=pending both
        // hides it from public listings and puts it back in the same
        // queue a brand-new posting waits in.
        if ($this->reportable instanceof JobPosting) {
            $pendingCount = Report::query()
                ->where('reportable_type', JobPosting::class)
                ->where('reportable_id', $this->reportable->id)
                ->where('review_status', ReportStatus::Pending)
                ->count();

            if ($pendingCount >= 3) {
                $this->reportable->update(['moderation_status' => ModerationStatus::Pending]);
            }
        }

        $this->reset('reason');
        $this->submitted = true;

        $this->dispatch('modal-close', name: 'report-modal-'.$this->reportable->id);
    }

    public function render()
    {
        return view('livewire.report-button');
    }
}
