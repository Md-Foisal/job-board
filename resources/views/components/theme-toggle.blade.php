{{--
    The light/dark switch: a small square button in the top bar showing
    the theme it will switch to. It reads and writes Flux's own
    appearance store ($flux.dark), the one the Settings > Appearance page
    binds to, so the two always agree; this switch only ever sets light
    or dark, and "system" stays on that page for anyone who wants it.

    A toggle button keeps one name and reports its state through
    aria-pressed (WAI-ARIA Authoring Practices, Button pattern), so the
    label says what it is, not what it will do.
--}}
<button
    type="button"
    x-data
    x-on:click="$flux.dark = ! $flux.dark"
    x-cloak
    x-bind:aria-pressed="$flux.dark.toString()"
    aria-label="{{ __('Dark theme') }}"
    {{ $attributes->class(['inline-flex size-9 shrink-0 items-center justify-center rounded-control border border-line bg-surface text-ink transition-colors duration-150 hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent dark:hover:bg-zinc-800']) }}
>
    <span class="flex" x-show="! $flux.dark"><flux:icon.moon class="size-[18px]" /></span>
    <span class="flex" x-show="$flux.dark"><flux:icon.sun class="size-[18px]" /></span>
</button>
