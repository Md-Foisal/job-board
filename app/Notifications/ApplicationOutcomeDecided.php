<?php

namespace App\Notifications;

use App\Enums\ApplicationOutcomeStatus;
use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The company's decision, told to the candidate in plain words.
 *
 * A "no" is said as a no -- not dressed up as an update they have to
 * decode -- and thanks them for their time. It names the company and the
 * job, never the person who decided.
 */
class ApplicationOutcomeDecided extends Notification
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
        $replace = ['company' => $job->company->name, 'title' => $job->title];

        $mail = (new MailMessage)->greeting(__('Hello!'));

        if ($this->application->outcome_status === ApplicationOutcomeStatus::Hired) {
            return $mail
                ->subject(__('Good news from :company', $replace))
                ->line(__(':company has marked your application for :title as hired. Congratulations!', $replace))
                ->action(__('See your application'), route('candidate.applications.show', $this->application));
        }

        return $mail
            ->subject(__('Your application to :company', $replace))
            ->line(__(':company has decided not to go ahead with your application for :title.', $replace))
            ->line(__('Thank you for applying. Your profile stays yours, and other companies can still hear from you when you apply.'))
            ->action(__('See your application'), route('candidate.applications.show', $this->application));
    }
}
