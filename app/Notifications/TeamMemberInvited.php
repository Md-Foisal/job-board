<?php

namespace App\Notifications;

use App\Models\Invitation;
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

        return (new MailMessage)
            ->subject(__('You have been invited to join :company', ['company' => $company]))
            ->greeting(__('Hello!'))
            ->line(__(':inviter invited you to join :company on :app.', [
                'inviter' => $this->invitation->invitedBy?->name ?? $company,
                'company' => $company,
                'app' => config('app.name'),
            ]))
            ->action(__('View invitation'), route('invitations.show', $this->invitation->token))
            ->line(__('This invitation expires on :date.', [
                'date' => $this->invitation->expires_at->toFormattedDateString(),
            ]));
    }
}
