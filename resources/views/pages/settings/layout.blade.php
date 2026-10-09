{{--
    The settings sections as tabs under the page header, rather than a
    second sidebar beside the first: one vertical navigation per screen.
    Each section heads its own cards, so the tab's name is not repeated
    as a heading under it.

    @param string $current  account, security or appearance -- named by the
                            page rather than read from the route, which a
                            Livewire update request does not carry.
    @param string|null $workspace  The company slug the page was opened
                                   from, carried along so the tabs keep
                                   the same frame (layouts/settings).
--}}
@props(['current', 'workspace' => null])

@php
    $keep = filled($workspace) ? ['company' => $workspace] : [];
@endphp

<div class="flex flex-col gap-6">
    <x-tab-nav :label="__('Settings')">
        <x-tab-nav.item :href="route('profile.edit', $keep)" :current="$current === 'account'">{{ __('Account') }}</x-tab-nav.item>
        <x-tab-nav.item :href="route('security.edit', $keep)" :current="$current === 'security'">{{ __('Security') }}</x-tab-nav.item>
        <x-tab-nav.item :href="route('appearance.edit', $keep)" :current="$current === 'appearance'">{{ __('Appearance') }}</x-tab-nav.item>
    </x-tab-nav>

    {{ $slot }}
</div>
