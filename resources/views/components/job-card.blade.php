@props(['jobPosting', 'matchScore' => null, 'showSaveButton' => false])

{{-- A <div> instead of one big <a> -- the company name below needs its own
     real link to the company profile, and nested <a> tags are invalid HTML
     with unpredictable click behavior. The "stretched link" pattern below
     (an invisible full-card <a> underneath, real links layered on top with
     z-10) makes the whole card clickable to the job while still letting the
     company name click through to its own destination. --}}
<div
    class="group relative flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-5 transition hover:border-brand-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-brand-700"
>
    <a href="{{ route('jobs.show', $jobPosting) }}" class="absolute inset-0" aria-label="{{ $jobPosting->title }}"></a>

    @if ($showSaveButton)
        <div class="absolute right-4 top-4 z-10">
            <livewire:save-job-button :job-posting="$jobPosting" :key="'save-'.$jobPosting->id" />
        </div>
    @endif

    {{-- Reserve room for the absolute Save button (below) when it's shown --
         truncate on the title needs the button's width excluded from its
         available space, or long titles render underneath the button
         instead of stopping short of it. --}}
    <div class="flex items-start gap-3 {{ $showSaveButton ? 'pr-28' : '' }}">
        <div class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-brand-50 text-sm font-semibold text-brand-700 dark:bg-brand-950 dark:text-brand-300">
            @if ($jobPosting->company->logo_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::url($jobPosting->company->logo_path) }}" alt="{{ $jobPosting->company->name }}" class="size-full object-cover">
            @else
                {{ \Illuminate\Support\Str::of($jobPosting->company->name)->substr(0, 1) }}
            @endif
        </div>
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                <h3 class="truncate font-display text-base font-semibold text-zinc-900 group-hover:text-brand-700 dark:text-zinc-100 dark:group-hover:text-brand-400">
                    {{ $jobPosting->title }}
                </h3>
                <x-match-score :score="$matchScore" />
            </div>
            <a
                href="{{ route('companies.show', $jobPosting->company) }}"
                wire:navigate
                class="relative z-10 block w-fit max-w-full truncate text-sm text-zinc-600 hover:text-brand-700 hover:underline dark:text-zinc-400 dark:hover:text-brand-400"
            >
                {{ $jobPosting->company->name }}
            </a>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2 text-xs">
        <span class="rounded-full bg-zinc-100 px-2.5 py-1 font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
            {{ $jobPosting->workplace_type->label() }}
        </span>
        <span class="rounded-full bg-zinc-100 px-2.5 py-1 font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
            {{ $jobPosting->employment_type->label() }}
        </span>
        @if ($jobPosting->location_city)
            <span class="text-zinc-500 dark:text-zinc-500">{{ $jobPosting->location_city }}</span>
        @endif
    </div>

    @if ($jobPosting->salary_negotiable || $jobPosting->salary_min || $jobPosting->salary_max)
        <p class="font-display text-sm font-semibold tabular-nums text-brand-700 dark:text-brand-400">
            @if ($jobPosting->salary_negotiable)
                Negotiable
            @else
                {{ $jobPosting->salary_currency }} {{ number_format($jobPosting->salary_min) }}–{{ number_format($jobPosting->salary_max) }}
                <span class="font-normal text-zinc-500 dark:text-zinc-500">/ {{ \Illuminate\Support\Str::lower($jobPosting->salary_period->label()) }}</span>
            @endif
        </p>
    @endif
</div>
