{{-- One option in the command palette's list: a link the arrow keys can highlight. --}}
<a
    id="{{ $id }}"
    href="{{ $entry['url'] }}"
    wire:navigate
    data-palette-option
    role="option"
    aria-selected="false"
    x-on:mousemove="hover($el)"
    x-on:click="close()"
    class="group flex items-center gap-3 rounded-control px-3 py-2 text-sm transition-colors aria-selected:bg-surface"
>
    <span class="flex size-8 shrink-0 items-center justify-center rounded-control border border-line bg-canvas transition-colors group-aria-selected:border-line-strong">
        <flux:icon :icon="$entry['icon']" class="size-4 text-ink-muted" aria-hidden="true" />
    </span>
    <span class="min-w-0 flex-1 truncate font-medium text-ink">{{ $entry['label'] }}</span>
    @if (filled($entry['meta']))
        <span class="max-w-[45%] shrink-0 truncate text-meta text-ink-muted">{{ $entry['meta'] }}</span>
    @endif
</a>
