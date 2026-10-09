<?php

namespace App\Notifications;

use App\Models\CompanyReview;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a company's owners and managers that its answer to a review was
 * not published, and why. The review itself is never quoted: the mail
 * goes to an inbox, the review is on the page.
 */
class ReviewResponseRejected extends Notification
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
            ->subject(__('Your answer to a review was not published'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->greetingName()]))
            ->line(__('We read :company\'s answer to one of its reviews and could not publish it as written:', [
                'company' => $this->review->company->name,
            ]))
            ->line($this->reason)
            ->line(__('Anyone who manages the company can write the answer again.'))
            ->action(__('Open your reviews'), route('employer.reviews', $this->review->company));
    }
}
