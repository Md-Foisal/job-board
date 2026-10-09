<?php

namespace App\Notifications;

use App\Filament\Resources\CompanyReviews\CompanyReviewResource;
use App\Models\CompanyReview;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A company has answered a review, or changed its answer, and the answer
 * waits for staff before it appears.
 */
class ReviewResponseAwaitingReview extends Notification
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
            ->subject(__('Company response to check: :company', ['company' => $this->review->company->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->greetingName()]))
            ->line(__(':company answered one of its reviews. The answer stays hidden until someone on the team approves it.', [
                'company' => $this->review->company->name,
            ]))
            ->action(__('Open the responses'), CompanyReviewResource::getUrl('index', ['tab' => 'responses']));
    }
}
