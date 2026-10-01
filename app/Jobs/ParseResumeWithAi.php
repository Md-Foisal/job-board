<?php

namespace App\Jobs;

use App\Ai\Agents\ResumeParser;
use App\Enums\AiFeature;
use App\Models\Document;
use App\Models\User;
use App\Support\AiQuota;
use App\Support\CvText;
use App\Support\ResumeDraft;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Files\Document as AiDocument;
use RuntimeException;
use Throwable;

/**
 * Has the AI read a CV in the background and leaves its suggestions in
 * the cache for the import page, which polls for them.
 *
 * The page marks the reading as running for two minutes. However this job
 * ends -- an answer, a failure, or a worker that never picked it up --
 * the page stops waiting: an answer or a failure is written here, and a
 * job that never ran simply lets the running mark expire.
 *
 * A PDF is sent as the file itself, so the model sees its layout. Claude
 * does not take Word files, so for a DOCX the text the product already
 * reads is sent instead. Nothing is sent that the candidate did not ask to
 * have read, and nothing is written to their profile: the suggestions wait
 * an hour for the candidate to review, then disappear.
 *
 * The run is counted against the candidate's allowance only once the
 * model has answered. It is tried once: retrying a spend-limit or
 * rate-limit refusal would only fail again, and the page offers the
 * candidate a new try instead.
 */
class ParseResumeWithAi implements ShouldQueue
{
    use Queueable;

    public const RUNNING_SECONDS = 120;

    public const RESULT_SECONDS = 3600;

    public const DAILY_ATTEMPTS = 5;

    public int $tries = 1;

    /**
     * Below the queue's retry_after (90 seconds), so a slow run is never
     * handed to a second worker while the first is still waiting on the
     * model -- which would read, and charge for, the same CV twice. The
     * model call itself gives up at 60.
     */
    public int $timeout = 75;

    public function __construct(public int $documentId, public int $userId) {}

    public static function resultKey(int $documentId): string
    {
        return "resume-import:{$documentId}";
    }

    public static function runningKey(int $documentId): string
    {
        return "resume-import:{$documentId}:running";
    }

    public static function attemptsKey(User $user): string
    {
        return "resume-import-ai:{$user->id}";
    }

    public function handle(): void
    {
        $document = Document::withTrashed()->findOrFail($this->documentId);
        $user = User::findOrFail($this->userId);

        if (! AiQuota::allows(AiFeature::ResumeParser, $user)) {
            $this->finish(['status' => 'unavailable']);

            return;
        }

        $response = ResumeParser::make()->prompt(...$this->prompt($document));

        AiQuota::record(AiFeature::ResumeParser, $user, null, $response);

        $this->finish([
            'status' => 'done',
            'draft' => ResumeDraft::fromAiAnswer($response->toArray())->toArray(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->finish(['status' => 'failed']);
    }

    /**
     * @return array{0: string, attachments?: array<int, AiDocument>}
     */
    private function prompt(Document $document): array
    {
        if (str_ends_with(strtolower($document->file_path), '.pdf')) {
            return [
                'Read the attached CV.',
                'attachments' => [AiDocument::fromStorage($document->file_path, 'local')],
            ];
        }

        $text = CvText::of($document);

        if ($text === '') {
            throw new RuntimeException('The CV has no text to send.');
        }

        return ["Read this CV:\n\n<cv>\n{$text}\n</cv>"];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function finish(array $result): void
    {
        Cache::put(self::resultKey($this->documentId), $result, self::RESULT_SECONDS);
        Cache::forget(self::runningKey($this->documentId));
    }
}
