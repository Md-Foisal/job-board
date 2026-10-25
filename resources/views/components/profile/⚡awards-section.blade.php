<?php

use Livewire\Attributes\Computed;
use Livewire\Component;

/*
 * The Awards section of the candidate's own profile: honours and awards
 * with who gave them, when, and a line on what for -- LinkedIn's
 * "Honors & awards".
 */
new class extends Component {
    public int $level = 2;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $title = '';

    public ?string $issuer = null;

    public ?string $awardedOn = null;

    public ?string $description = null;

    #[Computed]
    public function awards()
    {
        return auth()->user()->candidateProfile->awards()->newestFirst()->get();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $award = auth()->user()->candidateProfile->awards()->findOrFail($id);
        $this->authorize('update', $award);

        $this->resetValidation();
        $this->editingId = $award->id;
        $this->title = $award->title;
        $this->issuer = $award->issuer;
        $this->awardedOn = $award->awarded_on?->format('Y-m-d');
        $this->description = $award->description;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'issuer' => ['nullable', 'string', 'max:255'],
            'awardedOn' => ['nullable', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'awardedOn' => __('date'),
        ]);

        $data = [
            'title' => $validated['title'],
            'issuer' => $validated['issuer'],
            'awarded_on' => $validated['awardedOn'],
            'description' => $validated['description'],
        ];
        $profile = auth()->user()->candidateProfile;

        if ($this->editingId) {
            $award = $profile->awards()->findOrFail($this->editingId);
            $this->authorize('update', $award);
            $award->update($data);
        } else {
            $profile->awards()->create($data);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('profile-updated');
    }

    public function delete(): void
    {
        $award = auth()->user()->candidateProfile->awards()->findOrFail($this->editingId);
        $this->authorize('delete', $award);
        $award->delete();

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
        $this->title = '';
        $this->issuer = null;
        $this->awardedOn = null;
        $this->description = null;
        $this->resetValidation();
    }
}; ?>

<div>
    <x-profile.section id="awards" :heading="__('Awards')" :level="$level">
        @if ($this->awards->isNotEmpty())
            <x-slot:actions>
                <flux:button wire:click="create" size="sm" icon="plus">{{ __('Add award') }}</flux:button>
            </x-slot:actions>
        @endif

        @if ($this->awards->isEmpty())
            <div class="px-5 pb-5 sm:px-6">
                <x-empty-state icon="trophy" :level="$level + 1" :heading="__('No awards yet')">
                    {{ __('Prizes, rankings and honours: a first place in a course, a hackathon, a scholarship.') }}

                    <x-slot:actions>
                        <flux:button wire:click="create" variant="primary" size="sm" icon="plus">{{ __('Add award') }}</flux:button>
                    </x-slot:actions>
                </x-empty-state>
            </div>
        @else
            <ul class="divide-y divide-line border-t border-line">
                @foreach ($this->awards as $award)
                    <x-profile.entry-item icon="trophy" :title="$award->title" :subtitle="$award->issuer" :meta="$award->awarded_on?->format(\App\Support\DateFormat::MONTH)" :note="$award->description" wire:key="award-{{ $award->id }}">
                        <x-slot:actions>
                            <flux:button wire:click="edit({{ $award->id }})" variant="ghost" size="sm" icon="pencil" :aria-label="__('Edit :title', ['title' => $award->title])" />
                        </x-slot:actions>
                    </x-profile.entry-item>
                @endforeach
            </ul>
        @endif
    </x-profile.section>

    <flux:modal wire:model="showModal" class="w-full max-w-lg" @close="closeModal">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __('Edit award') : __('Add award') }}</flux:heading>

            <flux:input wire:model="title" :label="__('Award')" placeholder="First place, batch 8" />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input wire:model="issuer" :label="__('Given by')" :description="__('Optional')" />
                <flux:input type="date" wire:model="awardedOn" :label="__('Date')" :description="__('Optional')" />
            </div>

            <flux:textarea wire:model="description" :label="__('What it was for')" :description="__('Optional')" rows="3" maxlength="1000" />

            <div class="flex flex-wrap items-center justify-between gap-2">
                @if ($editingId)
                    <flux:button wire:click="delete" wire:confirm="{{ __('Delete this award from your profile?') }}" variant="ghost" icon="trash">{{ __('Delete') }}</flux:button>
                @endif

                <div class="ms-auto flex gap-2">
                    <flux:button wire:click="closeModal" variant="ghost">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</div>
