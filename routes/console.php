<?php

use App\Console\Commands\ExpireInvitations;
use App\Console\Commands\ExpireJobPostings;
use Illuminate\Support\Facades\Schedule;

// Every minute: each run is a single indexed UPDATE that usually matches
// nothing, and a company should not see "Active" on a posting that closed
// hours ago. Production needs the one cron entry that runs schedule:run.
Schedule::command(ExpireJobPostings::class)->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command(ExpireInvitations::class)->everyMinute()->withoutOverlapping()->onOneServer();
