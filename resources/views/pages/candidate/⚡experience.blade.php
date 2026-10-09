<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * The Experience section of the profile on a page of its own, for the links
 * that point straight at it (the CV builder's gaps, the CV import). The
 * section is the same one the profile page shows, so there is one place
 * where it is edited.
 */
new #[Layout('layouts::app')] #[Title('Experience')] class extends Component {}; ?>

<x-page width="narrow">
    <x-back-link :href="route('candidate.profile.edit')">{{ __('My profile') }}</x-back-link>

    <livewire:profile.experience-section :level="1" />
</x-page>
