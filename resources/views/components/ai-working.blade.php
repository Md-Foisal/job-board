{{--
    What a page shows while an AI request it started is still running:
    a spinner, what is happening, and how long it usually takes. A live
    region (role="status"), so a screen reader hears it once without
    moving focus. The page passes its own wire:poll, which checks for the
    result and swaps this out when it arrives.
--}}
@props(['heading'])

<div {{ $attributes->class('flex items-center gap-3 rounded-card border border-line bg-surface p-4 text-sm') }} role="status">
    <flux:icon.loading variant="mini" class="shrink-0 text-ink-muted" />
    <div>
        <p class="font-medium text-ink">{{ $heading }}</p>
        <p class="text-ink-muted">{{ $slot }}</p>
    </div>
</div>
