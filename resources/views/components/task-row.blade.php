{{--
    One line of a "Needs you" list: something waiting on the person, said
    in a few words, with the page where they act on it one click away.
    The whole row is the link, so it is easy to hit on a phone.

    @param string $href  where the work is done
    @param string $icon  a Heroicon name, for the kind of task
    Slot "detail": a second, quieter line -- how long it has waited, what
    was asked.
--}}
@props(['href', 'icon'])

<li>
    <a href="{{ $href }}" wire:navigate class="group flex items-center gap-4 px-5 py-4 transition hover:bg-surface sm:px-6">
        <x-icon-tile :icon="$icon" size="sm" />
        <span class="min-w-0 flex-1">
            <span class="block font-medium text-ink group-hover:text-sunset-small">{{ $slot }}</span>
            @isset($detail)
                <span {{ $detail->attributes->class('block text-sm text-ink-muted') }}>{{ $detail }}</span>
            @endisset
        </span>
        <flux:icon.chevron-right variant="mini" class="shrink-0 text-ink-muted" aria-hidden="true" />
    </a>
</li>
