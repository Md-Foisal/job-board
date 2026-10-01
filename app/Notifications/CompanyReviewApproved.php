<?php

namespace App\Notifications;

use App\Models\CompanyReview;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the writer their review is now on the company's page.
 */
class CompanyReviewApproved extends Notification
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
            ->subject(__('Your review of :company is published', ['company' => $this->review->company->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('Your review of :company\'s hiring process is now on its page, shown as "Verified applicant" with the month.', [
                'company' => $this->review->company->name,
            ]))
            ->action(__('See your review'), route('candidate.applications.show', $this->review->application_id));
    }
}
