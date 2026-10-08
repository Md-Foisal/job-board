{{-- The pay at the top of the apply card, the figure candidates look for
     first. Nothing at all when the posting states none. --}}
@props(['jobPosting'])

@php
    $pay = $jobPosting->payRange();
@endphp

@if ($jobPosting->salary_negotiable)
    <p {{ $attributes->class('text-subheading text-ink-muted') }}>{{ __('Pay negotiable') }}</p>
@elseif ($pay)
    <p {{ $attributes->class('font-display text-heading tabular-nums text-ink') }}>
        {{ $pay }}
        @if ($jobPosting->salary_period)
            <span class="text-body font-normal text-ink-muted">{{ $jobPosting->salary_period->per() }}</span>
        @endif
    </p>
@endif
