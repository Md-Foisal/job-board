<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * How much one source may create in a window, for the things that cost
 * someone else time or send mail in our name: accounts, applications,
 * job postings and team invitations.
 *
 * Only a finished creation is counted. A form that fails validation, or a
 * request turned away by the limit itself, uses nothing up -- the limit is
 * there to slow a script down, not to punish someone for a typo.
 *
 * The numbers sit well above what one honest person does:
 *
 * - Registrations are keyed by IP address, the only thing a visitor
 *   without an account has. Offices, campuses and mobile carriers put many
 *   people behind one address, so the ceiling allows a room full of
 *   sign-ups in an hour while stopping a script creating hundreds.
 * - Applications follow the daily cap large boards use (LinkedIn allows
 *   about fifty a day): past that, someone is spraying, and every one of
 *   those lands in a real recruiter's queue.
 * - New postings are counted per company, not per person, so adding
 *   teammates does not multiply the allowance. Editing an existing posting
 *   is never limited; creating and duplicating are.
 * - Invitations are counted per company because each one is an email to
 *   an address the company typed in. GitHub caps organisation invitations
 *   at fifty a day for the same reason: without it, a company set up a
 *   minute ago could use our mail server to write to anyone.
 * - Company review saves are counted per candidate, edits included: a
 *   review that enters the moderation queue mails every staff member, and
 *   deleting and rewriting one would otherwise do that without end. Ten
 *   leaves room to write a review and correct it several times.
 */
final class SubmissionLimits
{
    public const REGISTRATIONS_PER_HOUR = 10;

    public const APPLICATIONS_PER_DAY = 50;

    public const JOB_POSTINGS_PER_DAY = 20;

    public const INVITATIONS_PER_DAY = 50;

    public const REVIEW_SAVES_PER_DAY = 10;

    /**
     * Per company, since any of its owners and managers can answer. Each
     * save sends the response back to staff, so this is also what keeps a
     * save-and-delete loop from flooding the queue.
     */
    public const RESPONSE_SAVES_PER_DAY = 20;

    public static function registrationKey(string $ip): string
    {
        return 'registrations:'.$ip;
    }

    public static function applicationKey(User $candidate): string
    {
        return 'applications:'.$candidate->id;
    }

    public static function jobPostingKey(Company $company): string
    {
        return 'job-postings:'.$company->id;
    }

    public static function invitationKey(Company $company): string
    {
        return 'invitations:'.$company->id;
    }

    public static function reviewSaveKey(User $candidate): string
    {
        return 'company-review-saves:'.$candidate->id;
    }

    public static function responseSaveKey(Company $company): string
    {
        return 'review-response-saves:'.$company->id;
    }

    /**
     * The same words wherever a new posting is refused -- the form and the
     * duplicate action -- so the two can never drift apart.
     *
     * @return array{heading: string, text: string}
     */
    public static function jobPostingLimitMessage(Company $company): array
    {
        return [
            'heading' => __("You've reached today's posting limit"),
            'text' => trans_choice('{1} :company can start up to :limit new postings a day. You can add more in 1 hour; existing postings can still be edited.|[2,*] :company can start up to :limit new postings a day. You can add more in :count hours; existing postings can still be edited.', self::hoursUntilAvailable(self::jobPostingKey($company)), [
                'company' => $company->name,
                'limit' => self::JOB_POSTINGS_PER_DAY,
            ]),
        ];
    }

    /**
     * Whole minutes until the window reopens, never zero, so a message can
     * always say "in N minutes" truthfully.
     */
    public static function minutesUntilAvailable(string $key): int
    {
        return max(1, (int) ceil(RateLimiter::availableIn($key) / 60));
    }

    /**
     * Whole hours until the window reopens, never zero.
     */
    public static function hoursUntilAvailable(string $key): int
    {
        return max(1, (int) ceil(RateLimiter::availableIn($key) / 3600));
    }
}
