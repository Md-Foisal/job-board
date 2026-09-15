{{--
    One flat list of every hat this person wears: their own candidate side
    and each company they currently work for. Deliberately not two nested
    menus -- the two kinds of context behave identically here (each is just
    "take me to that space"), and the only real argument for nesting is
    routing differences we do not have.

    With a single context there is nothing to switch between, so the control
    hides itself rather than showing a menu with one entry in it.
--}}
@props(['current' => null])

@php
    $user = auth()->user();
    $companies = $user->activeCompanies;
    $hasCandidateSide = $user->isCandidate();
    $contextCount = $companies->count() + ($hasCandidateSide ? 1 : 0);
@endphp

@if ($contextCount > 1)
    <flux:dropdown position="bottom" align="start">
        <flux:button variant="subtle" icon:trailing="chevrons-up-down" class="max-w-52">
            <span class="truncate">{{ $current?->name ?? __('Personal') }}</span>
        </flux:button>

        <flux:menu>
            @if ($hasCandidateSide)
                <flux:menu.item
                    :href="route('candidate.dashboard')"
                    icon="user"
                    wire:navigate
                >
                    {{ __('Personal') }}
                </flux:menu.item>
            @endif

            @if ($hasCandidateSide && $companies->isNotEmpty())
                <flux:menu.separator />
            @endif

            @foreach ($companies as $company)
                <flux:menu.item
                    :href="route('employer.dashboard', $company)"
                    icon="building-office"
                    wire:navigate
                >
                    {{ $company->name }}
                </flux:menu.item>
            @endforeach
        </flux:menu>
    </flux:dropdown>
@else
    <span class="max-w-52 truncate font-display text-sm font-semibold text-zinc-900 dark:text-zinc-100">
        {{ $current?->name ?? __('Personal') }}
    </span>
@endif
