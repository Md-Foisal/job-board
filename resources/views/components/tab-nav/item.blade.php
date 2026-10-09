{{--
    One tab of x-tab-nav. Whether it is the current one is passed in
    rather than guessed from the address: a tab is often the same path
    with a different query string, which Flux's own matching ignores.

    A tab normally loads its address (wire:navigate). Inside a page that
    holds unsaved input next to the tabs -- a note half written beside
    the applicant's CV -- pass `action` instead: a Livewire expression
    such as "$set('tab', 'cv')". The tab then switches on the same page,
    the address still updates through the property's #[Url], and the
    href stays for opening the tab in a new window.
--}}
@props(['href', 'current' => false, 'action' => null])

@php
    $attributes = $attributes->merge($current ? ['aria-current' => 'page'] : []);
@endphp

@if ($action)
    <flux:navbar.item
        :href="$href"
        :current="$current"
        :accent="false"
        wire:click.prevent="{{ $action }}"
        :attributes="$attributes"
    >{{ $slot }}</flux:navbar.item>
@else
    <flux:navbar.item
        :href="$href"
        :current="$current"
        :accent="false"
        wire:navigate
        :attributes="$attributes"
    >{{ $slot }}</flux:navbar.item>
@endif
