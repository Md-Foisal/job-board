<?php

namespace App\Jobs;

use App\Ai\Agents\JobPostReviewer;
use App\Enums\AiFeature;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\User;
use App\Services\JobPerformance;
use App\Support\AiQuota;
use App\Support\JobPostReview;
use App\Support\JobPostReviewInput;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Has the AI review a job posting in the background and leaves the
 * checked answer in the cache, where the analytics page picks it up.
 *
 * The page marks the review as running for two minutes under the key it
 * asked for. However this job ends -- an answer, a failure, or a worker
 * that never picked it up -- the page stops waiting: an answer or a
 * failure is written here, and a job that never ran lets the running mark
 * expire.
 *
 * The answer is kept for a day, under a key tied to the posting and the
 * prompt as they were, so it stops showing the moment the posting is
 * edited. The run counts against the company's allowance once the model
 * has answered, and is tried once, as with every AI feature.
 */
class ReviewJobPostWithAi implements ShouldQueue
{
    use Queueable;

    public const RUNNING_SECONDS = 120;

    public const RESULT_SECONDS = 86400;

    /**
     * Per company, so a run that keeps failing -- and so costs no
     * allowance -- still cannot be retried without end.
     */
    public const DAILY_ATTEMPTS = 10;

    public int $tries = 1;

    /**
     * Below the queue's retry_after (90 seconds), so a slow run is never
     * handed to a second worker while the first still waits on the model.
     */
    public int $timeout = 75;

    public function __construct(public int $jobPostingId, public int $userId, public string $cacheKey) {}

    public static function runningKey(string $cacheKey): string
    {
        return "{$cacheKey}:running";
    }

    public static function attemptsKey(Company $company): string
    {
        return "ai-job-review-attempts:{$company->id}";
    }

    public function handle(JobPerformance $performance): void
    {
        $jobPosting = JobPosting::with('company')->findOrFail($this->jobPostingId);
        $company = $jobPosting->company;
        $user = User::findOrFail($this->userId);

        // The person may have left the team, or lost the right to edit
        // its postings, while this waited.
        if (! $user->can('update', $jobPosting)) {
            $this->finish(['status' => 'failed']);

            return;
        }

        if (! AiQuota::allows(AiFeature::JobPostReview, $user, $company)) {
            $this->finish(['status' => 'unavailable']);

            return;
        }

        $input = JobPostReviewInput::for($jobPosting, $performance->for($company, $jobPosting, JobPostReviewInput::RANGE_DAYS));

        $response = JobPostReviewer::make(JobPostReviewInput::material($input))->review();

        AiQuota::record(AiFeature::JobPostReview, $user, $company, $response);

        $answer = $response->toArray();
        $review = JobPostReview::fromAiAnswer($answer, $jobPosting->title);

        // An empty answer is a finding -- nothing to change. An answer the
        // checks emptied is not, and is shown as a failure.
        $saidSomething = ! empty($answer['issues']) || filled($answer['suggested_title'] ?? null);

        $this->finish($review->isEmpty() && $saidSomething
            ? ['status' => 'failed']
            : ['status' => 'done', 'review' => $review->toArray(), 'reviewed_at' => now()->toIso8601String()]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->finish(['status' => 'failed']);
    }

    /**
     * A failure is kept only as long as the running mark would have been:
     * long enough for the page to read it, not so long it hides a retry.
     *
     * @param  array<string, mixed>  $result
     */
    private function finish(array $result): void
    {
        Cache::put(
            $this->cacheKey,
            $result,
            $result['status'] === 'done' ? self::RESULT_SECONDS : self::RUNNING_SECONDS,
        );
        Cache::forget(self::runningKey($this->cacheKey));
    }
}
