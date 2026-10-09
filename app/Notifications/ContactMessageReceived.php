<?php

namespace App\Notifications;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Someone wrote through the contact form. The mail says what it is about
 * and nothing more: the message, and who sent it, are read in the panel,
 * not copied into every staff mailbox.
 */
class ContactMessageReceived extends Notification
{
    use Queueable;

    public function __construct(public ContactMessage $message) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('New message: :topic', ['topic' => $this->message->topic->label()]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->greetingName()]))
            ->line(__('Someone wrote to us through the contact form. They have been told to expect an answer within :days working days.', [
                'days' => ContactMessage::REPLY_WITHIN_WORKING_DAYS,
            ]))
            ->action(__('Open the inbox'), ContactMessageResource::getUrl('index'));
    }
}
