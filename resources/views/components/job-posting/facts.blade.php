{{-- "Job details" under the apply button: the facts a candidate checks
     before reading on, in the order Indeed's job page gives them. The
     closing day is the end of that day where the company is, said out
     loud only to someone whose own day ends at another time. A link to
     each category, unless links are off (the employer's preview, where
     leaving the form would lose the unsaved posting). --}}
@props(['jobPosting', 'links' => true])

@php
    $company = $jobPosting->company;
    $pay = $jobPosting->payRange();
    $location = collect([$jobPosting->location_city, $jobPosting->location_country])->filter()->implode(', ');
    $closesOn = \App\Support\ClosingDate::day($jobPosting);
    $closingZone = $company->timezone !== \App\Support\LocalTime::zone()
        ? \App\Support\LocalTime::label($company->timezone, at: $jobPosting->expires_at)
        : null;
@endphp

<dl {{ $attributes->class('grid grid-cols-[7rem_minmax(0,1fr)] gap-x-3 gap-y-2.5 text-sm') }}>
    <dt class="text-ink-muted">{{ __('Pay') }}</dt>
    <dd class="text-ink">
        @if ($jobPosting->salary_negotiable)
            {{ __('Negotiable') }}
        @elseif ($pay)
            {{ $pay }} {{ $jobPosting->salary_period?->per() }}
        @else
            {{ __('Not stated') }}
        @endif
    </dd>

    @if ($jobPosting->employment_type)
        <dt class="text-ink-muted">{{ __('Job type') }}</dt>
        <dd class="text-ink">{{ $jobPosting->employment_type->label() }}</dd>
    @endif

    @if ($jobPosting->workplace_type)
        <dt class="text-ink-muted">{{ __('Workplace') }}</dt>
        <dd class="text-ink">{{ $jobPosting->workplace_type->label() }}</dd>
    @endif

    @if ($location !== '')
        <dt class="text-ink-muted">{{ __('Location') }}</dt>
        <dd class="text-ink">{{ $location }}</dd>
    @endif

    @if ($jobPosting->min_experience_years !== null)
        <dt class="text-ink-muted">{{ __('Experience') }}</dt>
        <dd class="text-ink">
            {{ $jobPosting->min_experience_years === 0
                ? __('No minimum')
                : trans_choice('{1} :count+ year|[2,*] :count+ years', $jobPosting->min_experience_years) }}
        </dd>
    @endif

    @if ($jobPosting->categories->isNotEmpty())
        <dt class="text-ink-muted">{{ trans_choice('Category|Categories', $jobPosting->categories->count()) }}</dt>
        <dd class="text-ink">
            @foreach ($jobPosting->categories as $jobCategory)
                @if ($links)
                    <a href="{{ route('categories.show', $jobCategory) }}" class="hover:text-sunset-small hover:underline" wire:navigate>{{ $jobCategory->name }}</a>{{ $loop->last ? '' : ',' }}
                @else
                    {{ $jobCategory->name }}{{ $loop->last ? '' : ',' }}
                @endif
            @endforeach
        </dd>
    @endif

    @if ($jobPosting->published_at)
        <dt class="text-ink-muted">{{ __('Posted') }}</dt>
        <dd class="text-ink">{{ \App\Support\LocalTime::of($jobPosting->published_at)->format(\App\Support\DateFormat::DAY) }}</dd>
    @endif

    <dt class="text-ink-muted">{{ __('Closes') }}</dt>
    <dd class="text-ink">
        {{ $closesOn->format(\App\Support\DateFormat::DAY) }}
        @if ($closingZone)
            <span class="block text-meta text-ink-muted">{{ __('End of the day in :zone', ['zone' => $closingZone]) }}</span>
        @endif
    </dd>
</dl>
