<?php

namespace App\Notifications;

use App\Models\JobPosting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A posting sent back, with the reviewer's reason and the way forward:
 * editing it and publishing again puts it straight back into review.
 */
class JobPostingRejected extends Notification
{
    use Queueable;

    public function __construct(public JobPosting $jobPosting, public string $reason) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('":title" needs changes before it can go live', ['title' => $this->jobPosting->title]))
            ->greeting(__('Hello,'))
            ->line(__('Your posting ":title" was reviewed and cannot be published as it stands. The reviewer wrote:', ['title' => $this->jobPosting->title]))
            ->line('"'.$this->reason.'"')
            ->action(__('Edit the posting'), route('employer.jobs.edit', [$this->jobPosting->company, $this->jobPosting]))
            ->line(__('Once you publish it again it goes back for review.'));
    }
}
