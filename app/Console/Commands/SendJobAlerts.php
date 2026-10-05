<?php

namespace App\Console\Commands;

use App\Actions\SendJobAlert;
use App\Models\JobAlert;
use App\Support\LocalTime;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Emails every job alert whose turn has come. One alert failing -- a bad
 * address, a mail server hiccup -- is reported and skipped, so it cannot
 * stop everyone after it from getting theirs.
 *
 * With --local-hour, only people for whom it is that hour now: the
 * schedule runs it every hour with 8, so each candidate hears in their own
 * morning. An account with no zone yet counts as UTC, as its emails do.
 */
#[Signature('job-alerts:send {--local-hour= : Only people for whom it is this hour (0-23) now}')]
#[Description('Email candidates the new postings that match their job alerts')]
class SendJobAlerts extends Command
{
    public function handle(SendJobAlert $sendJobAlert): int
    {
        $hour = $this->option('local-hour');

        if ($hour !== null) {
            $hour = filter_var($hour, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 23]]);

            if ($hour === false) {
                $this->components->error('--local-hour takes a whole hour from 0 to 23.');

                return self::INVALID;
            }
        }

        $checked = 0;
        $failed = 0;

        $alerts = JobAlert::query()->due();

        if ($hour !== null) {
            $zones = LocalTime::zonesAtHour($hour);

            $alerts->whereHas('user', fn (Builder $user) => $user->where(fn (Builder $zone) => $zone
                ->whereIn('timezone', $zones)
                ->when(in_array(config('app.timezone'), $zones, true), fn (Builder $zone) => $zone->orWhereNull('timezone'))));
        }

        $alerts->with('user')->chunkById(100, function ($jobAlerts) use ($sendJobAlert, &$checked, &$failed) {
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
