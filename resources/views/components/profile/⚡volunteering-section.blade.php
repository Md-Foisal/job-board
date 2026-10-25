<?php

use Livewire\Attributes\Computed;
use Livewire\Component;

/*
 * The Volunteering section of the candidate's own profile: unpaid work
 * for a cause or a community, kept apart from paid roles as LinkedIn and
 * Europass do.
 */
new class extends Component {
    public int $level = 2;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $role = '';

    public string $organization = '';

    public ?string $startDate = null;

    public ?string $endDate = null;

    public ?string $description = null;

    #[Computed]
    public function roles()
    {
        return auth()->user()->candidateProfile->volunteerExperiences()->newestFirst()->get();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $role = auth()->user()->candidateProfile->volunteerExperiences()->findOrFail($id);
        $this->authorize('update', $role);

        $this->resetValidation();
        $this->editingId = $role->id;
        $this->role = $role->role;
        $this->organization = $role->organization;
        $this->startDate = $role->start_date?->format('Y-m-d');
        $this->endDate = $role->end_date?->format('Y-m-d');
        $this->description = $role->description;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'role' => ['required', 'string', 'max:255'],
            'organization' => ['required', 'string', 'max:255'],
            'startDate' => ['nullable', 'required_with:endDate', 'date', 'before_or_equal:today'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'startDate' => __('start date'),
            'endDate' => __('end date'),
        ]);

        $data = [
            'role' => $validated['role'],
            'organization' => $validated['organization'],
            'start_date' => $validated['startDate'],
            'end_date' => $validated['endDate'],
            'description' => $validated['description'],
        ];
        $profile = auth()->user()->candidateProfile;

        if ($this->editingId) {
            $role = $profile->volunteerExperiences()->findOrFail($this->editingId);
            $this->authorize('update', $role);
            $role->update($data);
        } else {
            $profile->volunteerExperiences()->create($data);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('profile-updated');
    }

    public function delete(): void
    {
        $role = auth()->user()->candidateProfile->volunteerExperiences()->findOrFail($this->editingId);
        $this->authorize('delete', $role);
        $role->delete();

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('profile-updated');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->role = '';
        $this->organization = '';
        $this->startDate = null;
        $this->endDate = null;
        $this->description = null;
        $this->resetValidation();
    }
}; ?>

<div>
    <x-profile.section id="volunteering" :heading="__('Volunteering')" :level="$level">
        @if ($this->roles->isNotEmpty())
            <x-slot:actions>
                <flux:button wire:click="create" size="sm" icon="plus">{{ __('Add volunteering') }}</flux:button>
            </x-slot:actions>
        @endif

        @if ($this->roles->isEmpty())
            <div class="px-5 pb-5 sm:px-6">
                <x-empty-state icon="hand-raised" :level="$level + 1" :heading="__('No volunteering yet')">
                    {{ __('Unpaid work for a cause or a community: mentoring, organising events, teaching.') }}

                    <x-slot:actions>
                        <flux:button wire:click="create" variant="primary" size="sm" icon="plus">{{ __('Add volunteering') }}</flux:button>
                    </x-slot:actions>
                </x-empty-state>
            </div>
        @else
            <ul class="divide-y divide-line border-t border-line">
                @foreach ($this->roles as $role)
                    <x-profile.entry-item icon="hand-raised" :title="$role->role" :subtitle="$role->organization"
                        :meta="$role->start_date ? $role->start_date->format(\App\Support\DateFormat::MONTH).' – '.($role->end_date?->format(\App\Support\DateFormat::MONTH) ?? __('Present')) : null"
                        :note="$role->description" wire:key="volunteering-{{ $role->id }}">
                        <x-slot:actions>
                            <flux:button wire:click="edit({{ $role->id }})" variant="ghost" size="sm" icon="pencil" :aria-label="__('Edit :title', ['title' => $role->role])" />
                        </x-slot:actions>
                    </x-profile.entry-item>
                @endforeach
            </ul>
        @endif
    </x-profile.section>

    <flux:modal wire:model="showModal" class="w-full max-w-lg" @close="closeModal">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __('Edit volunteering') : __('Add volunteering') }}</flux:heading>

            <flux:input wire:model="role" :label="__('Role')" placeholder="Mentor" />
            <flux:input wire:model="organization" :label="__('Organisation')" />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input type="date" wire:model="startDate" :label="__('Start date')" :description="__('Optional')" />
                <flux:input type="date" wire:model="endDate" :label="__('End date')" :description="__('Leave empty if you still do it')" />
            </div>

            <flux:textarea wire:model="description" :label="__('What you did')" :description="__('Optional')" rows="3" maxlength="1000" />

            <div class="flex flex-wrap items-center justify-between gap-2">
                @if ($editingId)
                    <flux:button wire:click="delete" wire:confirm="{{ __('Delete this from your profile?') }}" variant="ghost" icon="trash">{{ __('Delete') }}</flux:button>
                @endif

                <div class="ms-auto flex gap-2">
                    <flux:button wire:click="closeModal" variant="ghost">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</div>
