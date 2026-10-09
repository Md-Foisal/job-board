<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::settings')] #[Title('Appearance settings')] class extends Component {
    /**
     * The company workspace these settings were opened from, if any.
     */
    #[Url(as: 'company')]
    public ?string $workspace = null;
}; ?>

<x-page width="narrow">
    @include('partials.settings-heading')

    {{-- The top bar's switch flips between light and dark; following the
         device's own setting is chosen here. --}}
    <x-pages::settings.layout current="appearance" :workspace="$workspace">
        <x-card class="space-y-6">
            <flux:heading size="lg" level="2">{{ __('Theme') }}</flux:heading>

            <flux:radio.group x-data variant="segmented" x-model="$flux.appearance" aria-label="{{ __('Theme') }}">
                <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
                <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
                <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
            </flux:radio.group>

            <flux:text>{{ __('System follows the light or dark setting of the device you are using.') }}</flux:text>
        </x-card>
    </x-pages::settings.layout>
</x-page>
