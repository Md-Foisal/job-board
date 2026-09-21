<?php

namespace App\Console\Commands;

use App\Actions\SendJobAlert;
use App\Models\JobAlert;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Emails every job alert whose turn has come. One alert failing -- a bad
 * address, a mail server hiccup -- is reported and skipped, so it cannot
 * stop everyone after it from getting theirs.
 */
#[Signature('job-alerts:send')]
#[Description('Email candidates the new postings that match their job alerts')]
class SendJobAlerts extends Command
{
    public function handle(SendJobAlert $sendJobAlert): int
    {
        $checked = 0;
        $failed = 0;

        JobAlert::query()->due()->with('user')->chunkById(100, function ($jobAlerts) use ($sendJobAlert, &$checked, &$failed) {
            foreach ($jobAlerts as $jobAlert) {
                try {
                    $sendJobAlert($jobAlert);
                    $checked++;
                } catch (Throwable $exception) {
                    report($exception);
                    $failed++;
                }
            }
        });

        $this->components->info("Checked {$checked} job ".str('alert')->plural($checked).", {$failed} failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
