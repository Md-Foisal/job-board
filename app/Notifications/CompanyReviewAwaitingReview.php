<?php

namespace App\Notifications;

use App\Filament\Resources\CompanyReviews\CompanyReviewResource;
use App\Models\CompanyReview;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A company review has joined the moderation queue, new or edited. It
 * says which company, never who wrote it: staff see that in the panel,
 * where it belongs, not in a mailbox.
 */
class CompanyReviewAwaitingReview extends Notification
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
            ->subject(__('New company review to check: :company', ['company' => $this->review->company->name]))
            ->greeting(__('Hello,'))
            ->line(__('An applicant wrote a review of :company\'s hiring process. It stays hidden until someone on the team approves it.', [
                'company' => $this->review->company->name,
            ]))
            ->action(__('Open the review queue'), CompanyReviewResource::getUrl('index'));
    }
}
