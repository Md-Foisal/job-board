<?php

namespace App\Notifications;

use App\Models\CompanyReview;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the people who speak for a company that a review of its hiring
 * process is now public. It never says who wrote it, which job it was
 * about or how it ended: the company sees what the public sees.
 */
class CompanyReviewPublished extends Notification
{
    use Queueable;

    public function __construct(public CompanyReview $review) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('A new review of :company\'s hiring process', ['company' => $this->review->company->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('A verified applicant reviewed :company\'s hiring process. It is now on your company page.', [
                'company' => $this->review->company->name,
            ]))
            ->action(__('See your company page'), route('companies.show', $this->review->company));
    }
}
