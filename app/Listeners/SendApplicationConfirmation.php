<?php

namespace App\Listeners;

use App\Events\JobApplicationSubmitted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Notifications\ApplicationSubmittedNotification;

class SendApplicationConfirmation
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(JobApplicationSubmitted $event): void
    {
        $event->application->user->notify(new ApplicationSubmittedNotification($event->application));
    }
}
