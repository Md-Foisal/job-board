<?php

namespace App\Notifications;

use App\Filament\Resources\JobPostings\JobPostingResource;
use App\Models\JobPosting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A posting has joined the review queue. Without this, staff only find
 * out by opening the panel, and an employer's first posting -- the one
 * that decides whether they come back -- waits on someone happening to
 * look.
 */
class JobPostingAwaitingReview extends Notification
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
            ->subject(__('New posting to review: :title', ['title' => $this->jobPosting->title]))
            ->greeting(__('Hello,'))
            ->line(__(':company submitted ":title" for review.', [
                'company' => $this->jobPosting->company->name,
                'title' => $this->jobPosting->title,
            ]))
            ->action(__('Open the review queue'), JobPostingResource::getUrl('index'));
    }
}
