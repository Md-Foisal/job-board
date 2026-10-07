<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Staff's answer to a contact message, sent from the site's own address
 * rather than from the staff member's, so nobody's personal mailbox is
 * handed out. Their own message comes back under the answer, as mail
 * replies usually quote what they answer.
 */
class ContactReply extends Notification
{
    use Queueable;

    public function __construct(public ContactMessage $message) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('Re: your message to :app', ['app' => config('app.name')]))
            ->greeting(__('Hello :name,', ['name' => $this->message->name]));

        foreach (preg_split('/\R{2,}/', trim($this->message->reply_body)) as $paragraph) {
            $mail->line($paragraph);
        }

        return $mail
            ->line(__('You wrote: “:body”', ['body' => Str::limit($this->message->body, 500)]))
            ->line(__('If there is anything else, write to us again through the contact page.'))
            ->action(__('Contact us'), route('contact'));
    }
}
