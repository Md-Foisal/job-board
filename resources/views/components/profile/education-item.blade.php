{{--
    One school or course on a candidate's profile: where, what was
    studied, and when. The same entry for the candidate and for an
    employer reading it; the candidate's edit button goes in "actions".
--}}
@props(['record'])

<li {{ $attributes->class('flex gap-4 px-5 py-4 sm:px-6') }}>
    <x-icon-tile icon="academic-cap" size="sm" />

    <div class="min-w-0 flex-1">
        <p class="font-medium text-ink">{{ $record->institution_name }}</p>
        @if ($record->degree || $record->field_of_study)
            <p class="text-sm text-ink-soft">{{ collect([$record->degree, $record->field_of_study])->filter()->join(', ') }}</p>
        @endif
        <p class="mt-0.5 text-sm text-ink-muted">
            {{ $record->start_date->format(\App\Support\DateFormat::MONTH) }} &ndash; {{ $record->end_date?->format(\App\Support\DateFormat::MONTH) ?? __('Present') }}
        </p>
    </div>

    @isset($actions)
        <div class="shrink-0">{{ $actions }}</div>
    @endisset
</li>
