{{--
    "Closes in 2 days" on an open posting in its last days, wherever the
    posting is shown -- its card and its page -- so a candidate never
    finds out on the apply button. The window is the one the employer's
    own reminder uses (JobPostingChecks::EXPIRING_WITHIN_DAYS); earlier
    than that, a closing date is not news. The closing moment is already
    the end of the closing day in the company's zone (ClosingDate).
--}}
@props(['jobPosting'])

@if ($jobPosting->isOpen() && $jobPosting->expires_at->lte(now()->addDays(\App\Support\JobPostingChecks::EXPIRING_WITHIN_DAYS)))
    <flux:badge color="amber" size="sm" icon="clock">
        {{ __('Closes in :time', ['time' => $jobPosting->expires_at->diffForHumans(null, true)]) }}
    </flux:badge>
@endif
