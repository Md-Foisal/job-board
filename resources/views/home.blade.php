<x-layouts::guest>
    <section class="px-6 py-16 text-center">
        <h1 class="font-display text-3xl font-bold tracking-tight text-zinc-900 md:text-4xl dark:text-zinc-50">
            Find work that fits your skills
        </h1>
        <p class="mx-auto mt-3 max-w-xl text-zinc-600 dark:text-zinc-400">
            Browse and search freely — sign up only when you apply.
        </p>
    </section>

    <section class="mx-auto max-w-6xl px-6 pb-16">
        <div class="mb-6 flex items-center justify-between">
            <h2 class="font-display text-xl font-semibold text-zinc-900 dark:text-zinc-50">Recent openings</h2>
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
</x-layouts::guest>
