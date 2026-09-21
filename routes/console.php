<?php

use App\Console\Commands\AnonymizeDeletedUsers;
use App\Console\Commands\ExpireInvitations;
use App\Console\Commands\ExpireJobPostings;
use App\Console\Commands\SendJobAlerts;
use Illuminate\Support\Facades\Schedule;

// Every minute: each run is a single indexed UPDATE that usually matches
// nothing, and a company should not see "Active" on a posting that closed
// hours ago. Production needs the one cron entry that runs schedule:run.
Schedule::command(ExpireJobPostings::class)->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command(ExpireInvitations::class)->everyMinute()->withoutOverlapping()->onOneServer();

// Once a day, in the morning where the candidates are. Weekly alerts ride
// the same run and simply wait until their week is up (JobAlert::due()).
Schedule::command(SendJobAlerts::class)->dailyAt('08:00')->timezone('Asia/Dhaka')->withoutOverlapping()->onOneServer();

// Nightly, while few people are on: accounts past their grace period.
Schedule::command(AnonymizeDeletedUsers::class)->dailyAt('03:00')->timezone('Asia/Dhaka')->withoutOverlapping()->onOneServer();
