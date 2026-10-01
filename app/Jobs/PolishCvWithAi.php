<?php

namespace App\Jobs;

use App\Ai\Agents\CvWriter;
use App\Enums\AiFeature;
use App\Models\User;
use App\Support\AiQuota;
use App\Support\CvSuggestions;
use App\Support\CvWritingInput;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Has the AI suggest CV wording in the background and leaves the checked
 * suggestions in the cache, where the CV builder picks them up.
 *
 * The page marks the run as going for two minutes. However this job ends
 * -- suggestions, a failure, or a worker that never picked it up -- the
 * page stops waiting: a result is written here, and a job that never ran
 * lets the running mark expire.
 *
 * Suggestions wait an hour for the candidate to choose from; nothing is
 * written to the profile until they apply what they ticked. The run
 * counts against the candidate's allowance once the model has answered,
 * and is tried once.
 */
class PolishCvWithAi implements ShouldQueue
{
    use Queueable;

    public const RUNNING_SECONDS = 120;

    public const RESULT_SECONDS = 3600;

    public const DAILY_ATTEMPTS = 5;

    public int $tries = 1;

    /**
     * Below the queue's retry_after (90 seconds), so a slow run is never
     * handed to a second worker while the first still waits on the model.
     */
    public int $timeout = 75;

    public function __construct(public int $userId) {}

    public static function resultKey(int $candidateProfileId): string
    {
        return "ai-cv:{$candidateProfileId}";
    }

    public static function runningKey(int $candidateProfileId): string
    {
        return self::resultKey($candidateProfileId).':running';
    }

    public static function attemptsKey(User $user): string
    {
        return "ai-cv-attempts:{$user->id}";
    }

    public function handle(): void
    {
        $user = User::findOrFail($this->userId);
        $profile = $user->candidateProfile;

        if ($profile === null) {
            return;
        }

        if (! AiQuota::allows(AiFeature::CvBuilder, $user)) {
            $this->finish($profile->id, ['status' => 'unavailable']);

            return;
        }

        $response = CvWriter::make(CvWritingInput::material(CvWritingInput::for($profile)))->write();

        AiQuota::record(AiFeature::CvBuilder, $user, null, $response);

        $suggestions = CvSuggestions::fromAiAnswer($response->toArray(), $profile);

        $this->finish($profile->id, $suggestions->isEmpty()
            ? ['status' => 'failed']
            : ['status' => 'done', 'suggestions' => $suggestions->toArray(), 'expires_at' => now()->addSeconds(self::RESULT_SECONDS)->getTimestamp()]);
    }

    public function failed(?Throwable $exception): void
    {
        $profileId = User::find($this->userId)?->candidateProfile?->id;

        if ($profileId !== null) {
            $this->finish($profileId, ['status' => 'failed']);
        }
    }

    /**
     * A failure is kept only as long as the running mark would have been:
     * long enough for the page to read it, not so long it hides a retry.
     *
     * @param  array<string, mixed>  $result
     */
    private function finish(int $profileId, array $result): void
    {
        Cache::put(
            self::resultKey($profileId),
            $result,
            $result['status'] === 'done' ? self::RESULT_SECONDS : self::RUNNING_SECONDS,
        );
        Cache::forget(self::runningKey($profileId));
    }
}
