<x-layouts::guest>
    <section class="relative overflow-hidden px-6 py-20 text-center sm:py-28">
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10">
            <div class="absolute left-1/2 top-0 h-[420px] w-[820px] -translate-x-1/2 -translate-y-1/3 rounded-full bg-brand-300/30 blur-3xl dark:bg-brand-800/20"></div>
        </div>

        <h1 class="text-balance font-display text-4xl font-bold tracking-tight text-zinc-900 sm:text-5xl dark:text-zinc-50">
            Find work that fits <span class="text-brand-600 dark:text-brand-400">your</span> skills
        </h1>
        <p class="mx-auto mt-4 max-w-xl text-lg text-zinc-600 dark:text-zinc-400">
            Browse and search freely — sign up only when you apply.
        </p>

        <div class="mt-8">
            <livewire:job-search-autocomplete variant="hero" />
        </div>

        @if ($openJobsCount > 0)
            <p class="mt-6 text-sm text-zinc-500 dark:text-zinc-500">
                <span class="font-semibold tabular-nums text-zinc-700 dark:text-zinc-300">{{ number_format($openJobsCount) }}</span>
                open {{ \Illuminate\Support\Str::plural('role', $openJobsCount) }}
                @if ($hiringCompaniesCount > 0)
                    from <span class="font-semibold tabular-nums text-zinc-700 dark:text-zinc-300">{{ number_format($hiringCompaniesCount) }}</span>
                    {{ \Illuminate\Support\Str::plural('company', $hiringCompaniesCount) }} hiring now
                @endif
            </p>
        @endif
    </section>

    @if ($categories->isNotEmpty())
        <section class="mx-auto max-w-6xl px-6 pb-16">
            <h2 class="mb-6 font-display text-xl font-semibold text-zinc-900 dark:text-zinc-50">Browse by category</h2>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($categories as $category)
                    <x-category-card :category="$category" />
                @endforeach
            </div>
        </section>
    @endif

    <section class="mx-auto max-w-6xl px-6 pb-16">
        <div class="mb-6 flex items-center justify-between">
            <h2 class="font-display text-xl font-semibold text-zinc-900 dark:text-zinc-50">Recent openings</h2>
            <flux:button href="{{ route('jobs.index') }}" variant="ghost" icon:trailing="arrow-right" wire:navigate>
                Browse all
            </flux:button>
        </div>

        @if ($jobPostings->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-300 px-6 py-12 text-center text-zinc-500 dark:border-zinc-700 dark:text-zinc-500">
                No open positions right now — check back soon.
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($jobPostings as $jobPosting)
                    <x-job-card :job-posting="$jobPosting" />
                @endforeach
            </div>
        @endif
    </section>
</x-layouts::guest>
