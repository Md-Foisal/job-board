<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Flux\Flux;

new class extends Component
{
    use WithFileUploads;

    public string $name = '';
    public string $identity_type = '';
    public string $website = '';
    public string $about = '';
    public ?int $founded_year = null;
    public string $location = '';

    // Files uploads
    public $logo = null;
    public $cover_photo = null;
    public $avatar = null;

    // Existing Files
    public $existing_logo = null;
    public $existing_cover_photo = null;
    public $existing_avatar = null;

    public function mount(): void
    {
        $employerProfile = Auth::user()->employerProfile;

        
        $this->name = $employerProfile?->name ?? '';
        $this->identity_type = $employerProfile?->identity_type?->value ?? '';
        $this->website = $employerProfile?->website ?? '';
        $this->about = $employerProfile?->about ?? '';
        $this->founded_year = $employerProfile?->founded_year ?? null;
        $this->location = $employerProfile?->location ?? '';

        // Existing Files
        $this->existing_logo = $employerProfile?->logo;
        $this->existing_cover_photo = $employerProfile?->cover_photo;
        $this->existing_avatar = Auth::user()->avatar;
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'identity_type' => 'required|in:individual,company',
            'website' => 'nullable|url|max:255',
            'about' => 'nullable|string',
            'founded_year' => 'nullable|integer|min:1800|max:' . date('Y'),
            'location' => 'nullable|string|max:255',
            'logo' => 'nullable|image|max:2048',
            'cover_photo' => 'nullable|image|max:2048',
            'avatar' => 'nullable|image|max:2048',
        ]);

        $data = [
            'name' => $this->name,
            'identity_type' => $this->identity_type,
            'website' => $this->website,
            'about' => $this->about,
            'founded_year' => $this->founded_year,
            'location' => $this->location,
        ];

        if ($this->logo) {
            $this->logo = $this->logo->store('logos', 'public');
            $this->existing_logo = $this->logo;
            $data['logo'] = $this->logo;
        }

        if ($this->cover_photo) {
            $this->cover_photo = $this->cover_photo->store('cover_photos', 'public');
            $this->existing_cover_photo = $this->cover_photo;
            $data['cover_photo'] = $this->cover_photo;
        }

        if ($this->avatar) {
            $this->avatar = $this->avatar->store('avatars', 'public');
            $this->existing_avatar = $this->avatar;
            Auth::user()->update(['avatar' => $this->avatar]);
        }

        Auth::user()->employerProfile()->updateOrCreate([], $data);

        Flux::toast(variant: 'success', text: 'Profile saved.');
        $this->reset(['logo', 'cover_photo', 'avatar']);
    }
};
?>

<section>
    <flux:heading size="xl">My Profile</flux:heading>
    <flux:text class="mt-2">Set up your employer profile.</flux:text>

    <form wire:submit="save" class="mt-6 space-y-6">

        <flux:input wire:model="name" label="Name" placeholder="Enter your employer name" required />

        <flux:radio.group wire:model="identity_type" label="Type" required>
            <flux:radio value="individual" label="Individual" />
            <flux:radio value="company" label="Company" />
        </flux:radio.group>

        <flux:input wire:model="website" label="Website" placeholder="https://example.com" />
        <flux:input wire:model="about" label="About" placeholder="Tell us about your employer" />
        <flux:input wire:model="founded_year" label="Founded Year" placeholder="Enter the year your employer was founded" />
        <flux:input wire:model="location" label="Location" placeholder="Where is your employer based?" />

        <flux:input wire:model="logo" label="Logo" type="file" accept="image/*" />
        @if ($existing_logo)
            <div class="mt-2">
                <img src="{{ Storage::url($existing_logo) }}" alt="Existing Logo" class="h-16 w-16 object-cover rounded" />
            </div>  
        @endif

        <flux:input wire:model="cover_photo" label="Cover Photo" type="file" accept="image/*" />
        @if ($existing_cover_photo)
            <div class="mt-2">
                <img src="{{ Storage::url($existing_cover_photo) }}" alt="Existing Cover Photo" class="h-32 w-full object-cover rounded" />
            </div>
        @endif

        <flux:input wire:model="avatar" label="Avatar" type="file" accept="image/*" />
        @if ($existing_avatar)
            <div class="mt-2">
                <img src="{{ Storage::url($existing_avatar) }}" alt="Existing Avatar" class="h-10 w-10 object-cover rounded-full" />
            </div>
        @endif

        <flux:button variant="primary" type="submit">Save</flux:button>
    </form>
</section>
