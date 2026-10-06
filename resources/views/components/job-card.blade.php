@props(['jobPosting', 'matchScore' => null, 'showSaveButton' => false])

{{-- A <div> instead of one big <a> -- the company name below needs its own
     real link to the company profile, and nested <a> tags are invalid HTML
     with unpredictable click behavior. The "stretched link" pattern below
     (an invisible full-card <a> underneath, real links layered on top with
     z-10) makes the whole card clickable to the job while still letting the
     company name and the save button do their own thing. --}}
@php
    $where = collect([$jobPosting->location_city, $jobPosting->workplace_type->label()])->filter()->implode(' · ');
    $pay = $jobPosting->payRange();
@endphp

<x-card interactive class="group relative flex flex-col gap-4">
    <a href="{{ route('jobs.show', $jobPosting) }}" class="absolute inset-0 rounded-card" aria-label="{{ $jobPosting->title }}" wire:navigate></a>

    <div class="flex items-start gap-3">
        <x-company-logo :company="$jobPosting->company" />

        <div class="min-w-0 flex-1">
            {{-- Two lines before it gives up: the title is the one thing on
                 the card people scan for, and a phone has room for little
                 more than half of "Business Development Manager" on one. --}}
            <h3 class="line-clamp-2 text-subheading text-ink group-hover:text-sunset-small">
                {{ $jobPosting->title }}
            </h3>
            <div class="mt-0.5 flex max-w-full items-center gap-1">
                <a
                    href="{{ route('companies.show', $jobPosting->company) }}"
                    wire:navigate
                    class="relative z-10 block w-fit max-w-full truncate text-sm text-ink-muted hover:text-ink hover:underline"
                >
                    {{ $jobPosting->company->name }}
                </a>
                @if ($jobPosting->company->verified_at)
                    <x-verified-badge class="relative z-10" />
                @endif
            </div>
        </div>

        @if ($showSaveButton)
            <div class="relative z-10 -me-1 -mt-1">
                <livewire:save-job-button :job-posting="$jobPosting" compact :key="'save-'.$jobPosting->id" />
            </div>
        @endif
    </div>

    {{-- Where comes first: it rules a job in or out before anything else
         on the card does. --}}
    <div class="flex flex-wrap items-center gap-1.5">
        <x-match-score :score="$matchScore" size="md" />

        @if ($where !== '')
            <x-chip>
                <flux:icon.map-pin variant="micro" class="size-3.5" aria-hidden="true" />
                {{ $where }}
            </x-chip>
        @endif
        <x-chip>{{ $jobPosting->employment_type->label() }}</x-chip>
    </div>

    <div class="mt-auto flex items-baseline justify-between gap-3 border-t border-line pt-3">
        @if ($jobPosting->salary_negotiable)
            <p class="text-sm text-ink-muted">{{ __('Pay negotiable') }}</p>
        @elseif ($pay)
            <p class="min-w-0 text-sm font-semibold tabular-nums text-ink">
                {{ $pay }}
                @if ($jobPosting->salary_period)
                    <span class="font-normal text-ink-muted">{{ $jobPosting->salary_period->per() }}</span>
                @endif
            </p>
        @endif

        @if ($jobPosting->published_at)
            <time class="ms-auto shrink-0 text-meta text-ink-muted" datetime="{{ $jobPosting->published_at->toAtomString() }}">{{ $jobPosting->published_at->diffForHumans() }}</time>
        @endif
    </div>
</x-card>
