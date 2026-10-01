<?php

namespace App\Jobs;

use App\Ai\Agents\MatchExplainer;
use App\Enums\AiFeature;
use App\Models\JobPosting;
use App\Models\User;
use App\Services\MatchScoreCalculator;
use App\Support\AiQuota;
use App\Support\MatchExplanation;
use App\Support\MatchExplanationInput;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Has the AI explain a candidate's match with a job in the background and
 * leaves the checked answer in the cache, where the job page picks it up.
 *
 * The page marks the explanation as running for two minutes under the
 * key it asked for. However this job ends -- an answer, a failure, or a
 * worker that never picked it up -- the page stops waiting: an answer or
 * a failure is written here, and a job that never ran lets the running
 * mark expire.
 *
 * The answer is kept for a day. It is an assessment of a person, so it is
 * not stored anywhere that would need erasing later; and it is kept under
 * a key tied to the job, the profile and the prompt as they were, so it
 * stops showing the moment any of them changes.
 *
 * The run counts against the candidate's allowance once the model has
 * answered, and is tried once, as with reading a CV.
 */
class ExplainMatchWithAi implements ShouldQueue
{
    use Queueable;

    public const RUNNING_SECONDS = 120;

    public const RESULT_SECONDS = 86400;

    public const DAILY_ATTEMPTS = 20;

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

    public static function attemptsKey(User $user): string
    {
        return "ai-match-attempts:{$user->id}";
    }

    public function handle(MatchScoreCalculator $calculator): void
    {
        $jobPosting = JobPosting::findOrFail($this->jobPostingId);
        $user = User::findOrFail($this->userId);
        $profile = $user->candidateProfile;

        // The posting may have closed, or been hidden, while this waited.
        if ($profile === null || ! $jobPosting->isPubliclyVisible()) {
            $this->finish(['status' => 'failed']);

            return;
        }

        if (! AiQuota::allows(AiFeature::MatchExplanation, $user)) {
            $this->finish(['status' => 'unavailable']);

            return;
        }

        $input = MatchExplanationInput::for($jobPosting, $profile, $calculator->breakdown($jobPosting, $profile));

        $response = MatchExplainer::make(MatchExplanationInput::material($input))->explain();

        AiQuota::record(AiFeature::MatchExplanation, $user, null, $response);

        $explanation = MatchExplanation::fromAiAnswer($response->toArray());

        $this->finish($explanation->isEmpty()
            ? ['status' => 'failed']
            : ['status' => 'done', 'explanation' => $explanation->toArray()]);
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
