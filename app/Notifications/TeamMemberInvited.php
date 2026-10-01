<?php

namespace App\Notifications;

use App\Models\Invitation;
use App\Models\User;
use App\Support\LocalTime;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamMemberInvited extends Notification
{
    use Queueable;

    public function __construct(public Invitation $invitation) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $company = $this->invitation->company->name;

        // The invitee may have no account, and so no zone of their own;
        // the inviter's stands in, and is named, so the time is never
        // read in the wrong zone.
        $zone = LocalTime::zoneFor(
            User::firstWhere('email', $this->invitation->email) ?? $this->invitation->invitedBy,
        );

        return (new MailMessage)
            ->subject(__('You have been invited to join :company', ['company' => $company]))
            ->greeting(__('Hello!'))
            ->line(__(':inviter invited you to join :company on :app.', [
                'inviter' => $this->invitation->invitedBy?->name ?? $company,
                'company' => $company,
                'app' => config('app.name'),
            ]))
            ->action(__('View invitation'), route('invitations.show', $this->invitation->token))
            ->line(__('This invitation expires on :date (:zone).', [
                'date' => LocalTime::of($this->invitation->expires_at, $zone)->format('j F Y, g:i a'),
                'zone' => LocalTime::label($zone, at: $this->invitation->expires_at),
            ]));
    }
}
