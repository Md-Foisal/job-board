<?php

namespace App\Jobs;

use App\Ai\Agents\ReviewScreener;
use App\Enums\AiFeature;
use App\Enums\ModerationStatus;
use App\Enums\ReviewPart;
use App\Models\CompanyReview;
use App\Models\User;
use App\Support\AiQuota;
use App\Support\ReviewScreening;
use App\Support\ReviewScreeningInput;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Has the AI read a review, or a company's answer to one, while it waits
 * for staff, and leaves its hint on the row for the moderation queue.
 *
 * This is the platform moderating itself, so the run is recorded under
 * the person who wrote the text but spends no one's allowance. It runs
 * only while the text still waits for a decision, and its hint is kept
 * only if the text is still the one it read: an edit in the meantime
 * clears the hint and queues a run of its own.
 *
 * A run that fails leaves no hint, which is what staff see with the AI
 * switched off -- the review waits for a person either way. Tried once,
 * as with every AI feature.
 */
class ScreenReviewWithAi implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * Below the queue's retry_after (90 seconds), so a slow run is never
     * handed to a second worker while the first still waits on the model.
     */
    public int $timeout = 75;

    public function __construct(public int $reviewId, public ReviewPart $part) {}

    /**
     * Queue a run for text just saved, once the save is committed. Nothing
     * is queued with the AI switched off.
     */
    public static function queueFor(CompanyReview $review, ReviewPart $part): void
    {
        if (AiQuota::enabled()) {
            static::dispatch($review->id, $part)->afterCommit();
        }
    }

    public function handle(): void
    {
        $review = CompanyReview::with('company')->find($this->reviewId);

        // Deleted, decided, or (for an answer) withdrawn while this waited.
        if ($review === null || ! $this->waiting($review)) {
            return;
        }

        $author = $this->author($review);

        if ($author === null || ! AiQuota::allows(AiFeature::ReviewScreening, $author)) {
            return;
        }

        $material = ReviewScreeningInput::material($review, $this->part);

        $response = ReviewScreener::make($material, $this->part)->screen();

        AiQuota::record(AiFeature::ReviewScreening, $author, null, $response);

        $screening = ReviewScreening::fromAiAnswer($response->toArray(), $this->part);

        if ($screening === null) {
            return;
        }

        DB::transaction(function () use ($material, $screening) {
            $review = CompanyReview::with('company')->lockForUpdate()->find($this->reviewId);

            if ($review === null
                || ! $this->waiting($review)
                || ReviewScreeningInput::material($review, $this->part) !== $material) {
                return;
            }

            $review->forceFill([$this->part->screeningColumn() => $screening->toArray()]);

            // updated_at stays the time the review's own text last changed:
            // the staff queue sorts and ages reviews by it.
            CompanyReview::withoutTimestamps(fn () => $review->save());
        });
    }

    private function waiting(CompanyReview $review): bool
    {
        return match ($this->part) {
            ReviewPart::Review => $review->moderation_status === ModerationStatus::Pending,
            ReviewPart::Response => $review->response_status === ModerationStatus::Pending && $review->response_body !== null,
        };
    }

    /**
     * Whoever wrote the text being read. A writer whose account is waiting
     * out its deletion grace period still counts; an answer whose writer
     * has since been erased has none.
     */
    private function author(CompanyReview $review): ?User
    {
        return match ($this->part) {
            ReviewPart::Review => $review->candidateProfile()->first()?->user()->withTrashed()->first(),
            ReviewPart::Response => $review->respondedBy()->withTrashed()->first(),
        };
    }
}
