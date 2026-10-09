<?php

namespace App\Notifications;

use App\Models\CompanyReview;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a review's writer that the company's answer is now under it.
 */
class CompanyReviewResponsePublished extends Notification
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
            ->subject(__(':company answered your review', ['company' => $this->review->company->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->greetingName()]))
            ->line(__(':company published an answer to your review of its hiring process. It appears under your review on the company\'s page.', [
                'company' => $this->review->company->name,
            ]))
            ->action(__('Read the answer'), route('candidate.applications.show', $this->review->application_id));
    }
}
