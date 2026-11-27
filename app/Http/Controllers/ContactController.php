<?php

namespace App\Http\Controllers;

use App\Enums\ContactTopic;
use App\Http\Requests\StoreContactMessageRequest;
use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\ContactMessageReceived;
use App\Support\SubmissionLimits;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
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

    /**
     * The hidden field that carries, encrypted, the moment the form was
     * shown. A script that posts without opening the page has none, and
     * one that opens it and posts at once is faster than anyone can type.
     */
    public const STARTED_FIELD = 'started';

    /**
     * Writing a name, an address and a message takes a person far longer
     * than this; a script takes less than a second.
     */
    public const MIN_SECONDS_TO_WRITE = 3;

    /**
     * The same address sending the same words again within this many days
     * is a repeat. The first copy is already in the inbox.
     */
    public const REPEAT_WINDOW_DAYS = 30;

    public function show(Request $request): View
    {
        $user = $request->user();

        return view('contact', [
            // ?topic=privacy from the privacy policy: the reason someone
            // came is already known, so it is already chosen.
            'topic' => ContactTopic::tryFrom((string) $request->query('topic'))?->value,
            'name' => $user?->name,
            'email' => $user?->email,
            'started' => self::startedToken(),
        ]);
    }

    public static function startedToken(?int $timestamp = null): string
    {
        return Crypt::encryptString((string) ($timestamp ?? now()->getTimestamp()));
    }

    public function store(StoreContactMessageRequest $request): RedirectResponse
    {
        $limitKey = SubmissionLimits::contactMessageKey($request->ip());

        if (RateLimiter::tooManyAttempts($limitKey, SubmissionLimits::CONTACT_MESSAGES_PER_HOUR)) {
            throw ValidationException::withMessages([
                'body' => trans_choice('{1} You have sent several messages in a short while. Please try again in 1 minute.|[2,*] You have sent several messages in a short while. Please try again in :count minutes.', SubmissionLimits::minutesUntilAvailable($limitKey)),
            ]);
        }

        // A script is told it worked and nothing is kept, so it has no
        // reason to try another way. A repeat is answered the same way:
        // the first copy already reached the inbox.
        if ($this->looksScripted($request) || $this->isRepeat($request)) {
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

    private function looksScripted(Request $request): bool
    {
        if (filled($request->input(self::HONEYPOT))) {
            return true;
        }

        try {
            $startedAt = (int) Crypt::decryptString((string) $request->input(self::STARTED_FIELD));
        } catch (DecryptException) {
            return true;
        }

        return now()->getTimestamp() - $startedAt < self::MIN_SECONDS_TO_WRITE;
    }

    private function isRepeat(StoreContactMessageRequest $request): bool
    {
        return ContactMessage::query()
            ->where('email', $request->validated('email'))
            ->where('body', $request->validated('body'))
            ->where('created_at', '>=', now()->subDays(self::REPEAT_WINDOW_DAYS))
            ->exists();
    }

    private function sent(string $email): RedirectResponse
    {
        return redirect()->route('contact')->with('contactSentTo', $email);
    }
}
