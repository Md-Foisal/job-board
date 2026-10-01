<?php

namespace App\Livewire;

use App\Enums\ReportStatus;
use App\Enums\ReviewRejectionReason;
use App\Models\Company;
use App\Models\CompanyReview;
use Flux\Flux;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Isolated "report this" action, embedded on job detail, the company
 * profile and each review on it -- a small modal form rather than a
 * dedicated page, per the established page inventory (Report has no page
 * of its own).
 */
class ReportButton extends Component
{
    public const REPORTS_PER_HOUR = 10;

    public Model $reportable;

    public string $reason = '';

    public function mount(Model $reportable): void
    {
        $this->reportable = $reportable;
    }

    /**
     * Worded to fit a company as well as a posting: the same button sits
     * on both pages.
     *
     * A review can only be taken down on a fixed list of grounds, so a
     * report on one names one of them. There is no "Other": the list is
     * every ground there is, and disagreeing with a review is not one.
     */
    public function getReasonsProperty(): array
    {
        if ($this->reportable instanceof CompanyReview) {
            return collect(ReviewRejectionReason::cases())
                ->mapWithKeys(fn (ReviewRejectionReason $reason) => [$reason->value => $reason->label()])
                ->all();
        }

        return [
            'spam' => __('Spam or fake'),
            'scam' => __('Scam or fraud'),
            'inappropriate' => __('Inappropriate content'),
            'other' => __('Other'),
        ];
    }

    public function submit(): void
    {
        // The button is only drawn for signed-in users, but the action can
        // be called without it; an anonymous report would count for nobody.
        abort_unless(auth()->check(), 403);

        $this->validate([
            'reason' => 'required|string|in:'.implode(',', array_keys($this->reasons)),
        ]);

        // Reports can take things out of public view, so sending them is
        // rate-limited per account: a burst from one person is someone
        // working through a competitor's listings, not a reader.
        $key = 'report:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, self::REPORTS_PER_HOUR)) {
            $this->addError('reason', __('You have sent a lot of reports in a short time. Please try again later.'));

            return;
        }

        RateLimiter::hit($key, 3600);

        // One open report per person per subject. Hiding counts people,
        // not reports, so a second one would change nothing -- and saying
        // it was received either way tells a repeat reporter nothing.
        $alreadyReported = $this->reportable->reports()
            ->where('reporter_id', auth()->id())
            ->where('review_status', ReportStatus::Pending)
            ->exists();

        if (! $alreadyReported) {
            $this->reportable->reports()->create([
                'reporter_id' => auth()->id(),
                'reason' => $this->reasons[$this->reason],
                'review_status' => ReportStatus::Pending,
            ]);
        }

        $this->reset('reason');

        Flux::toast(variant: 'success', text: __('Thanks. Our team will take a look.'));

        $this->dispatch('modal-close', name: $this->modalName());
    }

    /**
     * Unique per subject type as well as id: a company and a job posting
     * can share an id, and both buttons can be on the same page.
     */
    public function modalName(): string
    {
        return 'report-'.class_basename($this->reportable).'-'.$this->reportable->getKey();
    }

    public function subjectNoun(): string
    {
        return match (true) {
            $this->reportable instanceof Company => __('company'),
            $this->reportable instanceof CompanyReview => __('review'),
            default => __('listing'),
        };
    }

    /**
     * Said before the report is sent, not after: a reported review stays
     * up, and someone expecting it to vanish should know that first.
     */
    public function note(): ?string
    {
        return $this->reportable instanceof CompanyReview
            ? __('The review stays up while our team reads it, and comes down only if it breaks one of these rules. Disagreeing with what it says is not enough.')
            : null;
    }

    public function render()
    {
        return view('livewire.report-button');
    }
}
