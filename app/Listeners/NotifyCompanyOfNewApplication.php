<?php

namespace App\Listeners;

use App\Events\ApplicationSubmitted;
use App\Notifications\NewApplicationReceived;
use Illuminate\Support\Facades\Notification;

class NotifyCompanyOfNewApplication
{
    /**
     * Everyone currently able to act on the application hears about it --
     * owners and managers, not plain members, since a message to someone
     * who cannot post or decide is noise they will learn to ignore.
     *
     * An ended membership hears nothing: the same live check that closes
     * the door closes the mailbox.
     */
    public function handle(ApplicationSubmitted $event): void
    {
        Notification::send(
            $event->application->jobPosting->company->decisionMakers(),
            new NewApplicationReceived($event->application),
        );
    }
}
