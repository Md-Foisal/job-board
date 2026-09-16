<?php

namespace App\Listeners;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Events\ApplicationSubmitted;
use App\Models\User;
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
        $company = $event->application->jobPosting->company;

        $recipients = User::query()
            ->whereHas('memberships', fn ($query) => $query
                ->where('company_id', $company->id)
                ->where('status', MembershipStatus::Active)
                ->whereIn('role', [MembershipRole::Owner, MembershipRole::Manager]))
            ->get();

        Notification::send($recipients, new NewApplicationReceived($event->application));
    }
}
