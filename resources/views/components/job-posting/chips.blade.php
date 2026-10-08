{{-- Where and how the job is done, as the first facts under its title:
     the job page and the employer's preview of it share this, so the
     preview cannot drift from what candidates see. Anything after the
     two chips -- when it was posted, when it closes -- goes in the slot. --}}
@props(['jobPosting'])

@php
    $where = collect([$jobPosting->location_city, $jobPosting->workplace_type?->label()])->filter()->implode(' · ');
@endphp

<div {{ $attributes->class('flex flex-wrap items-center gap-1.5') }}>
    @if ($where !== '')
        <x-chip>
            <flux:icon.map-pin variant="micro" class="size-3.5" aria-hidden="true" />
            {{ $where }}
        </x-chip>
    @endif
    @if ($jobPosting->employment_type)
        <x-chip>
            <flux:icon.briefcase variant="micro" class="size-3.5" aria-hidden="true" />
            {{ $jobPosting->employment_type->label() }}
        </x-chip>
    @endif
    {{ $slot }}
</div>
