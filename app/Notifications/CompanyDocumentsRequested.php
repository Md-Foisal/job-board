<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * What staff need before they can verify the company. There is no upload
 * page for it yet, so the reply goes to support by email.
 */
class CompanyDocumentsRequested extends Notification
{
    use Queueable;

    public function __construct(public Company $company, public string $request) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('One more step to verify :company', ['company' => $this->company->name]))
            ->greeting(__('Hello,'))
            ->line(__('Before :company can show the verified badge, our team needs:', ['company' => $this->company->name]))
            ->line('"'.$this->request.'"')
            ->line(__('Reply to this email with it attached.'))
            ->action(__('View your company profile'), route('employer.company.edit', $this->company));
    }
}
