@props(['jobPosting', 'detailed' => true, 'badge' => true])

@php
    $state = \App\Enums\PostingState::of($jobPosting);
@endphp

{{-- Where a posting stands, for its own company, as one pill: whether
     candidates can find it, and if not, why. Kept in one place so the
     dashboard, the postings list and the form never disagree about the
     same posting. Expects latestRejection loaded when detailed; a list
     that already shows the pill on the row's first line passes
     badge=false to add only the explanation under it. --}}
<div {{ $attributes }}>
    @if ($badge)
        <flux:badge :color="$state->color()" size="sm">{{ __($state->label()) }}</flux:badge>
    @endif

    @if ($detailed)
        @if ($state === \App\Enums\PostingState::NeedsChanges)
            {{-- The reason is the whole point of sending it back; a bare
                 "needs changes" leaves the employer guessing what to fix. --}}
            @if ($jobPosting->latestRejection?->reason)
                <flux:text size="sm" class="mt-2 max-w-prose whitespace-pre-line text-danger-700 dark:text-danger-300">{{ $jobPosting->latestRejection->reason }}</flux:text>
            @endif
            <flux:text size="sm" class="mt-1 max-w-prose">{{ __('Edit it and save to send it back for review.') }}</flux:text>
        @elseif ($state === \App\Enums\PostingState::HiddenForReview)
            {{-- Otherwise it reads as live while candidates cannot find it.
                 Who reported it, and why, stays with staff. --}}
            <flux:text size="sm" class="mt-1 max-w-prose">{{ __('Several people reported this posting. It is out of search until our team has looked; you do not need to do anything.') }}</flux:text>
        @endif
    @endif
</div>
