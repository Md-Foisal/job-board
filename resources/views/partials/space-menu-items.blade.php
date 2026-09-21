{{-- Every space this person can switch to, and the two ways to open a new
     one. claude/13 (question 4-c): picking a side at registration only
     decides which relationship comes first -- "if they pick wrong nothing
     breaks, they can build the other one too". These last two items are
     where they do that. Shared by the company workspace's switcher and
     the account menu everywhere else, so both list the same spaces. --}}
@php
    $spaceUser = auth()->user();
    $spaceCompanies = $spaceUser->activeCompanies;
    $spaceIsCandidate = $spaceUser->isCandidate();
@endphp

@if ($spaceIsCandidate)
    <flux:menu.item :href="route('candidate.dashboard')" icon="user" wire:navigate>
        {{ __('Personal') }}
    </flux:menu.item>
@endif

@foreach ($spaceCompanies as $spaceCompany)
    <flux:menu.item :href="route('employer.dashboard', $spaceCompany)" icon="building-office" wire:navigate>
        {{ $spaceCompany->name }}
    </flux:menu.item>
@endforeach

@if ($spaceIsCandidate || $spaceCompanies->isNotEmpty())
    <flux:menu.separator />
@endif

@unless ($spaceIsCandidate)
    <form method="POST" action="{{ route('candidate.start') }}" class="w-full">
        @csrf
        <flux:menu.item as="button" type="submit" icon="plus" class="w-full cursor-pointer">
            {{ __('Start a candidate profile') }}
        </flux:menu.item>
    </form>
@endunless

<flux:menu.item :href="route('companies.create')" icon="plus">
    {{ __('Create a company') }}
</flux:menu.item>
