@props(['category'])

<x-card
    as="a"
    interactive
    padding="none"
    href="{{ route('categories.show', $category) }}"
    wire:navigate
    class="group flex items-center gap-3 p-3 sm:p-4"
>
    <span class="icon-sunset-on-hover flex size-9 shrink-0 items-center justify-center rounded-control bg-surface text-ink ring-1 ring-line sm:size-10" aria-hidden="true">
        <flux:icon :name="\App\Support\CategoryIcon::for($category)" class="size-5" />
    </span>
    <span class="min-w-0 flex-1 text-sm font-medium leading-snug text-ink sm:text-body">
        {{ $category->name }}
    </span>
    <span class="font-mono text-meta tabular-nums text-ink-muted">
        <span aria-hidden="true">{{ number_format($category->job_postings_count) }}</span>
        <span class="sr-only">{{ trans_choice(':count open job|:count open jobs', $category->job_postings_count) }}</span>
    </span>
</x-card>
