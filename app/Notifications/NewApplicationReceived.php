<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The mirror of the candidate's notification: somebody applied, and the
 * people who can act on it hear about it straight away rather than in a
 * digest. A hiring team that finds out a day late is how an application
 * starts going stale, which is the thing this product is built against.
 */
class NewApplicationReceived extends Notification
{
    use Queueable;

    public function __construct(public Application $application) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $job = $this->application->jobPosting;

        return (new MailMessage)
            ->subject(__('New application for :title', ['title' => $job->title]))
            ->greeting(__('Hello!'))
            ->line(__(':name has applied for :title.', [
                'name' => $this->application->candidateProfile->user->name,
                'title' => $job->title,
            ]))
            ->action(__('Review the application'), route('employer.applications.show', [
                'company' => $job->company,
                'application' => $this->application,
            ]))
            ->line(__('Moving it along is what tells the candidate where they stand.'));
    }
}
