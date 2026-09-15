@props(['category'])

<a
    href="{{ route('categories.show', $category) }}"
    wire:navigate
    class="group flex items-center justify-between rounded-lg border border-zinc-200 bg-white px-4 py-3 transition hover:border-brand-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-brand-700"
>
    <span class="text-sm font-medium text-zinc-800 group-hover:text-brand-700 dark:text-zinc-200 dark:group-hover:text-brand-400">
        {{ $category->name }}
    </span>
    <span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-semibold tabular-nums text-brand-700 dark:bg-brand-950 dark:text-brand-300">
        {{ $category->job_postings_count }}
    </span>
</a>
