<?php

use App\Console\Commands\AnonymizeDeletedUsers;
use App\Console\Commands\ExpireInvitations;
use App\Console\Commands\ExpireJobPostings;
use App\Console\Commands\PruneExpiredCache;
use App\Console\Commands\SendJobAlerts;
use Illuminate\Support\Facades\Schedule;

// Every minute: each run is a single indexed UPDATE that usually matches
// nothing, and a company should not see "Active" on a posting that closed
// hours ago. Production needs the one cron entry that runs schedule:run.
Schedule::command(ExpireJobPostings::class)->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command(ExpireInvitations::class)->everyMinute()->withoutOverlapping()->onOneServer();

// Every hour, for the people whose morning it is: each candidate hears at
// about 8 their own time, wherever they are. Weekly alerts ride the same
// runs and simply wait until their week is up (JobAlert::due()).
Schedule::command(SendJobAlerts::class, ['--local-hour' => 8])->hourly()->withoutOverlapping()->onOneServer();

// Nightly, while few people are on: accounts past their grace period.
Schedule::command(AnonymizeDeletedUsers::class)->dailyAt('03:00')->timezone('Asia/Dhaka')->withoutOverlapping()->onOneServer();

// Hourly: the database cache store only drops an expired entry when the
// same key is read again, and AI drafts and explanations about a person
// must not outlive their hour or day.
Schedule::command(PruneExpiredCache::class)->hourly()->withoutOverlapping()->onOneServer();
