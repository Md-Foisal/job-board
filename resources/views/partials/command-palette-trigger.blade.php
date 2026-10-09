{{--
    What opens the command palette without a keyboard shortcut: a wide
    button that looks like a search box on larger screens, an icon on a
    phone. The shortcut it shows follows the device -- ⌘K on Apple
    devices, Ctrl K elsewhere -- and is left out where there is no
    keyboard to press it on.

    @param string $label    What the palette searches, said the same way as its own box.
    @param string $variant  'field' or 'icon'.
--}}
@if ($variant === 'field')
    <button
        type="button"
        x-data="{ mac: /mac|iphone|ipad|ipod/i.test(navigator.userAgentData?.platform || navigator.platform || '') }"
        x-on:click="$dispatch('open-command-palette')"
        aria-keyshortcuts="Meta+K Control+K"
        class="flex h-9 w-full max-w-sm items-center gap-2.5 rounded-control border border-line bg-canvas px-3 text-sm text-ink-muted transition-colors hover:border-line-strong hover:text-ink"
    >
        <flux:icon.magnifying-glass variant="mini" class="size-4 shrink-0" aria-hidden="true" />
        <span class="min-w-0 flex-1 truncate text-start">{{ $label }}</span>
        <kbd class="kbd hidden shrink-0 [@media(hover:hover)_and_(pointer:fine)]:inline-flex" x-text="mac ? '⌘K' : 'Ctrl K'" aria-hidden="true">Ctrl K</kbd>
    </button>
@else
    <flux:button x-data variant="ghost" size="sm" icon="magnifying-glass" x-on:click="$dispatch('open-command-palette')" :aria-label="$label" />
@endif
