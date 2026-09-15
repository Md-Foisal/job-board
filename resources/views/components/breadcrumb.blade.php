{{--
    The Breadcrumb molecule from claude/14 step ৩-খ, and the Supplemental
    navigation level from step ২-ঙ ("Home > Category > Job").

    It was hand-written inline on each page before this component existed,
    which meant a change to the separator -- or adding the aria markup a
    screen reader needs -- had to be remembered in every copy. Home is
    always the first crumb, so callers pass only what comes after it.

    @param array $items  [['label' => 'Jobs', 'url' => route('jobs.index')], ['label' => 'Senior Laravel Developer']]
                         An item with no 'url' is the current page: it is
                         rendered as plain text, not a link.
--}}
@props(['items' => []])

<nav aria-label="{{ __('Breadcrumb') }}" {{ $attributes->class('text-sm text-zinc-500 dark:text-zinc-500') }}>
    <a
        href="{{ route('home') }}"
        class="inline-flex items-center hover:text-brand-700 dark:hover:text-brand-400"
        wire:navigate
        title="{{ __('Home') }}"
        aria-label="{{ __('Home') }}"
    >
        <flux:icon.home variant="mini" class="size-4" />
    </a>

    @foreach ($items as $item)
        {{-- aria-hidden: the separator is decoration, and a screen reader
             reading "slash" between every crumb is noise. --}}
        <span class="mx-1" aria-hidden="true">/</span>

        @if (!empty($item['url']))
            <a href="{{ $item['url'] }}" class="hover:text-brand-700 dark:hover:text-brand-400" wire:navigate>
                {{ $item['label'] }}
            </a>
        @else
            <span class="text-zinc-700 dark:text-zinc-300" aria-current="page">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
