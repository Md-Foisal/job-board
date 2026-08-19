<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Flux\Flux;

new class extends Component {
    use WithFileUploads;

    public string $headline = '';
    public string $bio = '';
    public string $location = '';
    public ?int $experience_years = null;
    
    // Files upload
    public $resume = null;
    public $avatar = null;
    public $cover_photo = null;

    // Existing files
    public $existing_resume = null;
    public $existing_avatar = null;
    public $existing_cover_photo = null;

    public function mount(): void
    {
        $candidateProfile = Auth::user()->candidateProfile;

        $this->headline = $candidateProfile?->headline ?? '';
        $this->bio = $candidateProfile?->bio ?? '';
        $this->location = $candidateProfile?->location ?? '';
        $this->experience_years = $candidateProfile?->experience_years ?? null;

        // Existing files
        $this->existing_resume = $candidateProfile?->resume;
        $this->existing_avatar = $candidateProfile?->avatar;
        $this->existing_cover_photo = $candidateProfile?->cover_photo;
    }

    public function save(): void
    {
        $this->validate([
            'headline' => 'required|string|max:255',
            'bio' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'experience_years' => 'nullable|integer|min:0',
            'resume' => 'nullable|file|mimes:pdf,doc,docx|max:2048',
            'avatar' => 'nullable|image|max:2048',
            'cover_photo' => 'nullable|image|max:2048',
        ]);

        $data = [
            'headline' => $this->headline,
            'bio' => $this->bio,
            'location' => $this->location,
            'experience_years' => $this->experience_years,
        ];

        if ($this->resume) {
            $this->resume = $this->resume->store('resumes', 'public');
            $this->existing_resume = $this->resume;
            $data['resume'] = $this->resume;
        }

        if ($this->avatar) {
            $this->avatar = $this->avatar->store('avatars', 'public');
            $this->existing_avatar = $this->avatar;
            $data['avatar'] = $this->avatar;
        }

        if ($this->cover_photo) {
            $this->cover_photo = $this->cover_photo->store('cover_photos', 'public');
            $this->existing_cover_photo = $this->cover_photo;
            $data['cover_photo'] = $this->cover_photo;
        }

        Auth::user()->candidateProfile()->updateOrCreate([], $data);

        Flux::toast(variant: 'success', text: 'Profile saved.');
        $this->reset(['resume', 'avatar', 'cover_photo']);
    }
};
?>

<section>
    <flux:heading size="xl">My Profile</flux:heading>
    <flux:text class="mt-2">Set up your candidate profile.</flux:text>

    <form wire:submit="save" class="mt-6 space-y-6">

        <flux:input wire:model="headline" label="Headline" placeholder="E.g., Laravel Developer" />
        <flux:input wire:model="bio" label="Bio" placeholder="Tell us about yourself" />
        <flux:input wire:model="location" label="Location" placeholder="Where are you based?" />

        {{-- File uploads --}}
        <flux:input wire:model="resume" label="Resume" type="file" accept=".pdf,.doc,.docx" />
        @if ($existing_resume)
           <flux:text class="text-sm text-gray-500">
            Current Resume: <a href="{{ Storage::url($existing_resume) }}" target="_blank" class="text-blue-500 underline">View</a>
            </flux:text> 
        @endif

        <flux:input wire:model="avatar" label="Avatar" type="file" accept="image/*" />
        @if ($existing_avatar)
            <flux:text class="text-sm text-gray-500">
                Current Avatar: <img src="{{ Storage::url($existing_avatar) }}" alt="Avatar" class="inline-block w-10 h-10 rounded-full" />
            </flux:text>
        @endif

        <flux:input wire:model="cover_photo" label="Cover Photo" type="file" accept="image/*" />
        @if ($existing_cover_photo)
            <flux:text class="text-sm text-gray-500">
                Current Cover Photo: <img src="{{ Storage::url($existing_cover_photo) }}" alt="Cover Photo" class="inline-block w-10 h-10 rounded-full" />
            </flux:text>
        @endif

        <flux:input wire:model="experience_years" label="Experience (years)" placeholder="How many years of experience do you have?" />

        {{-- submit button --}}
        <flux:button variant="primary" type="submit">Save</flux:button>

    </form>
</section>