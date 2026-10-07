{{--
    One tab of x-tab-nav. Whether it is the current one is passed in
    rather than guessed from the address: a tab is often the same path
    with a different query string, which Flux's own matching ignores.
--}}
@props(['href', 'current' => false])

<flux:navbar.item
    :href="$href"
    :current="$current"
    :accent="false"
    wire:navigate
    :attributes="$attributes->merge($current ? ['aria-current' => 'page'] : [])"
>{{ $slot }}</flux:navbar.item>
