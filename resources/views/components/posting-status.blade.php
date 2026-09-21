@props(['jobPosting', 'detailed' => true])

{{-- Where a posting stands, for its own company: whether it is open, and
     whether candidates can actually see it. Kept in one place so the
     dashboard and the postings list never disagree about the same posting.
     Expects open_reporters_count, and latestRejection when detailed. --}}
<div {{ $attributes }}>
    <flux:badge :color="$jobPosting->availability_status === \App\Enums\AvailabilityStatus::Active ? 'green' : 'zinc'">
        {{ $jobPosting->availability_status->label() }}
    </flux:badge>

    {{-- Not on a draft. Moderation starts when a posting is submitted, so
         telling someone their unpublished draft is "in review" claims a
         queue it was never put in. --}}
    @if ($jobPosting->availability_status !== \App\Enums\AvailabilityStatus::Draft)
        @if ($jobPosting->moderation_status === \App\Enums\ModerationStatus::Pending)
            <flux:badge color="yellow">{{ __('In review') }}</flux:badge>
        @elseif ($jobPosting->moderation_status === \App\Enums\ModerationStatus::Rejected)
            <flux:badge color="red">{{ __('Needs changes') }}</flux:badge>

            {{-- The reason is the whole point of sending it back; a bare
                 "rejected" leaves the employer guessing what to fix. --}}
            @if ($detailed)
                @if ($jobPosting->latestRejection?->reason)
                    <flux:text size="sm" class="mt-2 max-w-sm whitespace-pre-line text-red-700 dark:text-red-400">{{ $jobPosting->latestRejection->reason }}</flux:text>
                @endif
                <flux:text size="sm" class="mt-1">{{ __('Edit and publish again to send it back for review.') }}</flux:text>
            @endif
        @elseif ($jobPosting->open_reporters_count >= \App\Models\Report::HIDE_AFTER_REPORTERS)
            {{-- Otherwise it reads as live while candidates cannot find it.
                 Who reported it, and why, stays with staff. --}}
            <flux:badge color="orange">{{ __('Hidden for review') }}</flux:badge>
            @if ($detailed)
                <flux:text size="sm" class="mt-1 max-w-sm">{{ __('Several people reported this posting. It is out of search until our team has looked; you do not need to do anything.') }}</flux:text>
            @endif
        @endif
    @endif
</div>
