<x-layouts::guest>
    <article class="mx-auto max-w-3xl px-6 py-12">
        <a href="{{ route('home') }}" class="text-sm text-brand-700 hover:underline dark:text-brand-400">&larr; Back to listings</a>

        <header class="mt-4 flex items-start gap-4 border-b border-zinc-200 pb-6 dark:border-zinc-800">
            <div class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-brand-50 text-lg font-semibold text-brand-700 dark:bg-brand-950 dark:text-brand-300">
                @if ($jobPosting->company->logo_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($jobPosting->company->logo_path) }}" alt="{{ $jobPosting->company->name }}" class="size-full object-cover">
                @else
                    {{ \Illuminate\Support\Str::of($jobPosting->company->name)->substr(0, 1) }}
                @endif
            </div>
            <div>
                <h1 class="font-display text-2xl font-bold text-zinc-900 dark:text-zinc-50">{{ $jobPosting->title }}</h1>
                <p class="mt-1 text-zinc-600 dark:text-zinc-400">{{ $jobPosting->company->name }}</p>

                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
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
            </div>
        </header>

        @if ($jobPosting->salary_negotiable || $jobPosting->salary_min || $jobPosting->salary_max)
            <p class="mt-6 font-display text-lg font-semibold tabular-nums text-brand-700 dark:text-brand-400">
                @if ($jobPosting->salary_negotiable)
                    Salary: Negotiable
                @else
                    {{ $jobPosting->salary_currency }} {{ number_format($jobPosting->salary_min) }}–{{ number_format($jobPosting->salary_max) }}
                    <span class="font-normal text-zinc-500 dark:text-zinc-500">/ {{ \Illuminate\Support\Str::lower($jobPosting->salary_period->label()) }}</span>
                @endif
            </p>
        @endif

        <div class="prose prose-zinc mt-6 max-w-none dark:prose-invert">
            {!! nl2br(e($jobPosting->description)) !!}
        </div>

        @if ($jobPosting->skills->isNotEmpty())
            <div class="mt-8 flex flex-wrap gap-2">
                @foreach ($jobPosting->skills as $skill)
                    <span class="rounded-full bg-brand-50 px-3 py-1 text-xs font-medium text-brand-700 dark:bg-brand-950 dark:text-brand-300">
                        {{ $skill->name }}
                    </span>
                @endforeach
            </div>
        @endif
    </article>
</x-layouts::guest>
