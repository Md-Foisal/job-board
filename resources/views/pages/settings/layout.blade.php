{{-- Settings sit inside the main sidebar layout, so their own sections
     are tabs across the top rather than a second sidebar beside the
     first: one vertical navigation per screen. --}}
<div class="flex flex-col">
    <flux:navbar scrollable class="-mt-3 mb-6 border-b border-line" aria-label="{{ __('Settings') }}">
        <flux:navbar.item :href="route('profile.edit')" wire:navigate>{{ __('Account') }}</flux:navbar.item>
        <flux:navbar.item :href="route('security.edit')" wire:navigate>{{ __('Security') }}</flux:navbar.item>
        <flux:navbar.item :href="route('appearance.edit')" wire:navigate>{{ __('Appearance') }}</flux:navbar.item>
    </flux:navbar>

    <div class="w-full">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        @if (filled($subheading ?? null))
            <flux:subheading>{{ $subheading }}</flux:subheading>
        @endif

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
