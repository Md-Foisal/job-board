<?php

namespace App\Notifications;

use App\Enums\ApplicationOutcomeStatus;
use App\Models\Application;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The company's decision, told to the candidate in plain words.
 *
 * Queued with a delay the length of the undo window, and sent only if the
 * very decision it announces still stands: not if it was undone, and not
 * twice if the company undid it and decided again (that decision queues
 * its own email, with its own decided_at).
 *
 * A "no" is said as a no and thanks them for applying. It names the
 * company and the job, never the person who decided.
 */
class ApplicationOutcomeDecided extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Application $application,
        public ApplicationOutcomeStatus $outcome,
        public CarbonInterface $decidedAt,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        // Someone who deleted their account inside the window asked to
        // hear nothing more from us.
        if (method_exists($notifiable, 'trashed') && $notifiable->trashed()) {
            return false;
        }

        $application = $this->application->fresh();

        return $application !== null
            && $application->outcome_status === $this->outcome
            // Whole seconds: the database keeps no more than that.
            && $application->decided_at?->timestamp === $this->decidedAt->timestamp;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $job = $this->application->jobPosting;
        $replace = ['company' => $job->company->name, 'title' => $job->title];

        $mail = (new MailMessage)->greeting(__('Hello!'));

        if ($this->outcome === ApplicationOutcomeStatus::Hired) {
            return $mail
                ->subject(__('Good news from :company', $replace))
                ->line(__(':company has marked your application for :title as hired. Congratulations!', $replace))
                ->action(__('See your application'), route('candidate.applications.show', $this->application));
        }

        return $mail
            ->subject(__('Your application for :title at :company', $replace))
            ->line(__('Thank you for applying for :title at :company. They have reviewed your application and decided not to move forward with it.', $replace))
            ->line(__('This does not affect any of your other applications, and you can keep applying to jobs here.'))
            ->action(__('See your application'), route('candidate.applications.show', $this->application));
    }
}
