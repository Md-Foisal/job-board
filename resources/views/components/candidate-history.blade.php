{{--
    A candidate's work and study history as an employer reads it: the
    Experience and Education sections of their profile, newest first. A
    section the candidate has left empty still shows, with a line saying
    so, so the reader can tell "nothing listed" from "nothing loaded".
--}}
@props(['experience', 'education'])

<x-card padding="none" class="overflow-hidden">
    <div class="px-5 py-5 sm:px-6">
        <flux:heading size="lg">{{ __('Experience') }}</flux:heading>
    </div>

    @if ($experience->isEmpty())
        <p class="border-t border-line px-5 py-4 text-sm text-ink-muted sm:px-6">{{ __('No work experience on their profile.') }}</p>
    @else
        <ul class="divide-y divide-line border-t border-line">
            @foreach ($experience as $record)
                <li class="flex gap-4 px-5 py-4 sm:px-6">
                    <x-icon-tile icon="briefcase" size="sm" />
                    <div class="min-w-0">
                        <p class="font-medium text-ink">{{ $record->job_title }}</p>
                        <p class="text-sm text-ink-soft">{{ $record->company_name }}</p>
                        <p class="mt-1 text-sm text-ink-muted">
                            {{ $record->start_date->format(\App\Support\DateFormat::MONTH) }} &mdash; {{ $record->end_date?->format(\App\Support\DateFormat::MONTH) ?? __('Present') }}
                        </p>
                        @if ($record->description)
                            <div class="prose-content mt-2 text-sm">{!! $record->description !!}</div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>

<x-card padding="none" class="overflow-hidden">
    <div class="px-5 py-5 sm:px-6">
        <flux:heading size="lg">{{ __('Education') }}</flux:heading>
    </div>

    @if ($education->isEmpty())
        <p class="border-t border-line px-5 py-4 text-sm text-ink-muted sm:px-6">{{ __('No education on their profile.') }}</p>
    @else
        <ul class="divide-y divide-line border-t border-line">
            @foreach ($education as $record)
                <li class="flex gap-4 px-5 py-4 sm:px-6">
                    <x-icon-tile icon="academic-cap" size="sm" />
                    <div class="min-w-0">
                        <p class="font-medium text-ink">{{ $record->institution_name }}</p>
                        @if ($record->degree || $record->field_of_study)
                            <p class="text-sm text-ink-soft">{{ collect([$record->degree, $record->field_of_study])->filter()->join(', ') }}</p>
                        @endif
                        <p class="mt-1 text-sm text-ink-muted">
                            {{ $record->start_date->format(\App\Support\DateFormat::MONTH) }} &mdash; {{ $record->end_date?->format(\App\Support\DateFormat::MONTH) ?? __('Present') }}
                        </p>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
