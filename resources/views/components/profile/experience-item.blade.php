{{--
    One role on a candidate's profile, the way LinkedIn lists it: the
    title, the company, the dates with how long the role lasted, then
    what they did there. The same entry for the candidate and for an
    employer reading it; the candidate's edit button goes in "actions".
--}}
@props(['record'])

@php($months = \App\Support\ExperienceDuration::ofRole($record))

<li {{ $attributes->class('flex gap-4 px-5 py-4 sm:px-6') }}>
    <x-icon-tile icon="briefcase" size="sm" />

    <div class="min-w-0 flex-1">
        <p class="font-medium text-ink">{{ $record->job_title }}</p>
        <p class="text-sm text-ink-soft">{{ $record->company_name }}</p>
        <p class="mt-0.5 text-sm text-ink-muted">
            {{ $record->start_date->format(\App\Support\DateFormat::MONTH) }} &ndash; {{ $record->end_date?->format(\App\Support\DateFormat::MONTH) ?? __('Present') }}
            @if ($months > 0)
                <span aria-hidden="true">&middot;</span> {{ \App\Support\ExperienceDuration::label($months) }}
            @endif
        </p>

        @if ($record->description)
            <div class="prose-content mt-2 text-sm">{!! $record->description !!}</div>
        @endif
    </div>

    @isset($actions)
        <div class="shrink-0">{{ $actions }}</div>
    @endisset
</li>
