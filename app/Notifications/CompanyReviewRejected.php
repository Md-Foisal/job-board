<?php

namespace App\Notifications;

use App\Models\CompanyReview;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the writer their review was held back, why, and that an edit
 * sends it for another check.
 */
class CompanyReviewRejected extends Notification
{
    use Queueable;

    public function __construct(public CompanyReview $review, public string $reason) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your review of :company was not published', ['company' => $this->review->company->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('We read your review of :company\'s hiring process and could not publish it as written:', [
                'company' => $this->review->company->name,
            ]))
            ->line($this->reason)
            ->line(__('You can edit it and send it for another check.'))
            ->action(__('Edit your review'), route('candidate.applications.show', $this->review->application_id));
    }
}
