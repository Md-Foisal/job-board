<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Flux\Flux;

new class extends Component {
    public string $headline = '';
    public string $bio = '';
    public string $location = '';
    public ?int $experience_years = null;

    public function mount(): void
    {
        $this->headline = Auth::user()->candidateProfile?->headline ?? '';
        $this->bio = Auth::user()->candidateProfile?->bio ?? '';
        $this->location = Auth::user()->candidateProfile?->location ?? '';
        $this->experience_years = Auth::user()->candidateProfile?->experience_years ?? null;
    }

    public function save(): void
    {
        Auth::user()->candidateProfile()->updateOrCreate([], [
            'headline' => $this->headline,
            'bio' => $this->bio,
            'location' => $this->location,
            'experience_years' => $this->experience_years,
        ]);

        Flux::toast(variant: 'success', text: 'Profile saved.');
    }
};
?>

<div>
    <flux:heading size="xl">My Profile</flux:heading>
    <flux:text class="mt-2">Set up your candidate profile.</flux:text>

    <form wire:submit="save" class="mt-6 space-y-6">
        <flux:input wire:model="headline" label="Headline" placeholder="E.g., Laravel Developer" />
        <flux:input wire:model="bio" label="Bio" placeholder="Tell us about yourself" />
        <flux:input wire:model="location" label="Location" placeholder="Where are you based?" />
        <flux:input wire:model="experience_years" label="Experience (years)" placeholder="How many years of experience do you have?" />
        <flux:button variant="primary" type="submit">Save</flux:button>
    </form>
</div>