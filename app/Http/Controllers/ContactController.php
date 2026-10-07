<?php

namespace App\Http\Controllers;

use App\Enums\ContactTopic;
use App\Http\Requests\StoreContactMessageRequest;
use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\ContactMessageReceived;
use App\Support\SubmissionLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ContactController extends Controller
{
    /**
     * The name of the hidden field only a form-filling script sees and
     * fills in. A browser user never does, since it is off screen and out
     * of the tab order.
     */
    public const HONEYPOT = 'website';

    public function show(Request $request): View
    {
        $user = $request->user();

        return view('contact', [
            // ?topic=privacy from the privacy policy: the reason someone
            // came is already known, so it is already chosen.
            'topic' => ContactTopic::tryFrom((string) $request->query('topic'))?->value,
            'name' => $user?->name,
            'email' => $user?->email,
        ]);
    }

    public function store(StoreContactMessageRequest $request): RedirectResponse
    {
        $limitKey = SubmissionLimits::contactMessageKey($request->ip());

        if (RateLimiter::tooManyAttempts($limitKey, SubmissionLimits::CONTACT_MESSAGES_PER_HOUR)) {
            throw ValidationException::withMessages([
                'body' => trans_choice('{1} You have sent several messages in a short while. Please try again in 1 minute.|[2,*] You have sent several messages in a short while. Please try again in :count minutes.', SubmissionLimits::minutesUntilAvailable($limitKey)),
            ]);
        }

        // A script that filled the hidden field is told it worked and
        // nothing is kept, so it has no reason to try another way.
        if (filled($request->input(self::HONEYPOT))) {
            return $this->sent($request->validated('email'));
        }

        $message = ContactMessage::create([
            ...$request->validated(),
            'user_id' => $request->user()?->id,
        ]);

        RateLimiter::hit($limitKey, 3600);

        Notification::send(User::query()->activeStaff()->get(), new ContactMessageReceived($message));

        return $this->sent($message->email);
    }

    private function sent(string $email): RedirectResponse
    {
        return redirect()->route('contact')->with('contactSentTo', $email);
    }
}
