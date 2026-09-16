<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The half of the ghosting-killer the candidate actually feels.
 *
 * The employer moved this to keep their own track of things; this mail is
 * that act reaching the person waiting on it, without anyone having to
 * remember to write to them. It names the company rather than the staff
 * member -- the candidate is owed the decision, not a name to chase.
 */
class ApplicationStageChanged extends Notification
{
    use Queueable;

    public function __construct(public Application $application) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $job = $this->application->jobPosting;
        $company = $job->company->name;

        return (new MailMessage)
            ->subject(__('Your application to :company has moved forward', ['company' => $company]))
            ->greeting(__('Hello!'))
            ->line(__(':company has moved your application for :title to :stage.', [
                'company' => $company,
                'title' => $job->title,
                'stage' => $this->application->stage->label(),
            ]))
            ->action(__('See where it stands'), route('candidate.applications.show', $this->application))
            ->line(__('You can follow every step of this application from that page.'));
    }
}
