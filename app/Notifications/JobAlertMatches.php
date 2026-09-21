<?php

namespace App\Notifications;

use App\Models\JobAlert;
use App\Support\JobSearchCriteria;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\Email;

/**
 * New postings that match a candidate's job alert.
 *
 * Carries the one-click unsubscribe headers (RFC 8058) that Gmail and
 * Yahoo require of anything sent in bulk: the mail client can switch the
 * alert off with a single POST, no sign-in, straight from its own button.
 */
class JobAlertMatches extends Notification
{
    use Queueable;

    public function __construct(
        public JobAlert $jobAlert,
        public Collection $jobPostings,
        public int $total,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // No expiry: an unsubscribe link has to keep working in an email
        // that sits in the inbox for months.
        $unsubscribeUrl = URL::signedRoute('job-alerts.unsubscribe', ['jobAlert' => $this->jobAlert->id]);
        [$skillNames, $categoryNames] = JobSearchCriteria::names([$this->jobAlert->criteria]);

        return (new MailMessage)
            ->subject(trans_choice('{1} :count new job for ":name"|[2,*] :count new jobs for ":name"', $this->total, [
                'name' => $this->jobAlert->name,
            ]))
            ->markdown('mail.job-alert', [
                'jobAlert' => $this->jobAlert,
                'jobPostings' => $this->jobPostings,
                'total' => $this->total,
                'searchedFor' => implode(' · ', JobSearchCriteria::describe($this->jobAlert->criteria, $skillNames, $categoryNames)),
                'allUrl' => route('jobs.index', $this->jobAlert->criteria),
                'manageUrl' => route('candidate.job-alerts.index'),
                'unsubscribeUrl' => $unsubscribeUrl,
            ])
            ->withSymfonyMessage(function (Email $message) use ($unsubscribeUrl) {
                $message->getHeaders()->addTextHeader('List-Unsubscribe', "<{$unsubscribeUrl}>");
                $message->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            });
    }
}
