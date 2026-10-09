<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

/**
 * The one answer to "send me a reset link", whatever happened.
 *
 * Fortify's own failure response says "We can't find a user with that
 * email address", which lets anyone test addresses until one belongs to
 * an account. So a link sent, an unknown address and a request too soon
 * after the last one all read the same; the person who owns the account
 * finds the link in their inbox either way.
 */
class PasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public function toResponse($request)
    {
        $message = __('If an account uses that email address, we have sent it a link to reset the password. It can take a minute to arrive.');

        return $request->wantsJson()
            ? new JsonResponse(['message' => $message], 200)
            : back()->with('status', $message);
    }
}
