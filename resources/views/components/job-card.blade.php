@props(['jobPosting', 'matchScore' => null])

<a
    href="{{ route('jobs.show', $jobPosting) }}"
    class="group flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-5 transition hover:border-brand-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-brand-700"
>
    <div class="flex items-start gap-3">
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
                @if (!is_null($matchScore))
                    <span class="shrink-0 rounded-full bg-success-50 px-2 py-0.5 text-xs font-semibold tabular-nums text-success-700 dark:bg-success-950 dark:text-success-300">
                        {{ $matchScore }}% match
                    </span>
                @endif
            </div>
            <p class="truncate text-sm text-zinc-600 dark:text-zinc-400">{{ $jobPosting->company->name }}</p>
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
</a>
