@props(['category'])

<div class="flex items-center justify-between rounded-lg border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900">
    <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $category->name }}</span>
    <span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-semibold tabular-nums text-brand-700 dark:bg-brand-950 dark:text-brand-300">
        {{ $category->job_postings_count }}
    </span>
</div>
