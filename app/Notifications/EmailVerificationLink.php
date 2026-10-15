<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The link that confirms an email address, sent through the queue so
 * registering does not wait for the mail provider.
 */
class EmailVerificationLink extends VerifyEmail implements ShouldQueue
{
    use Queueable;
}
