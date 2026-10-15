<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The password reset link, sent through the queue.
 *
 * Sent while the form waits, the reset request would take longer to answer
 * for an address that has an account (an email goes out) than for one that
 * does not (nothing does), and the difference tells a stranger which
 * addresses are registered. Queued, both are answered at once.
 */
class PasswordResetLink extends ResetPassword implements ShouldQueue
{
    use Queueable;
}
