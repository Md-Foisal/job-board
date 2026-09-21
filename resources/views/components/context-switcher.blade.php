{{--
    One flat list of every hat this person wears: their own candidate side
    and each company they currently work for. Deliberately not two nested
    menus -- the two kinds of context behave identically here (each is just
    "take me to that space"), and the only real argument for nesting is
    routing differences we do not have.

    Always a menu now, even with one space: it is also where a new company
    or a candidate profile is started (partials/space-menu-items).
--}}
@props(['current' => null])

<flux:dropdown position="bottom" align="start">
    <flux:button variant="subtle" icon:trailing="chevrons-up-down" class="max-w-52">
        <span class="truncate">{{ $current?->name ?? __('Personal') }}</span>
    </flux:button>

    <flux:menu>
        @include('partials.space-menu-items')
    </flux:menu>
</flux:dropdown>
