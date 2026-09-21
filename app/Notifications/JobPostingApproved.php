<?php

namespace App\Notifications;

use App\Models\JobPosting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The posting has cleared review and is live. The employer published it
 * and then waited; this closes that wait instead of leaving them to find
 * out by checking -- the same courtesy the platform asks employers to
 * show candidates.
 */
class JobPostingApproved extends Notification
{
    use Queueable;

    public function __construct(public JobPosting $jobPosting) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('":title" is live', ['title' => $this->jobPosting->title]))
            ->greeting(__('Good news.'))
            ->line(__('Your posting ":title" has been reviewed and is now visible to candidates.', ['title' => $this->jobPosting->title]))
            ->action(__('See it as candidates do'), route('jobs.show', $this->jobPosting));
    }
}
