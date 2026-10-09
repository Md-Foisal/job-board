<?php

use App\Models\Document;
use Livewire\Attributes\Computed;
use Livewire\Component;

/*
 * The Experience section of the candidate's own profile, edited where it
 * is read: a pencil on each role and an Add button open the same dialog,
 * as on LinkedIn. Used on the profile page and on the Experience page,
 * which is the same section on a page of its own for links that point
 * straight at it.
 */
new class extends Component {
    public int $level = 2;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $companyName = '';

    public string $jobTitle = '';

    public ?string $description = null;

    public string $startDate = '';

    public ?string $endDate = null;

    /**
     * Counts each opening of the dialog, so even adding twice in a row
     * starts from an empty editor.
     */
    public int $editorRound = 0;

    #[Computed]
    public function records()
    {
        return auth()->user()->candidateProfile
            ->experienceRecords()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
    }

    #[Computed]
    public function importableCv(): ?Document
    {
        return $this->records->isEmpty() ? auth()->user()->candidateProfile->importableCv() : null;
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $record = auth()->user()->candidateProfile->experienceRecords()->findOrFail($id);
        $this->authorize('update', $record);

        $this->resetValidation();
        $this->editingId = $record->id;
        $this->companyName = $record->company_name;
        $this->jobTitle = $record->job_title;
        $this->description = $record->description;
        $this->startDate = $record->start_date->format('Y-m-d');
        $this->endDate = $record->end_date?->format('Y-m-d');

        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'companyName' => ['required', 'string', 'max:255'],
            'jobTitle' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'startDate' => ['required', 'date'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
        ]);

        $data = [
            'company_name' => $validated['companyName'],
            'job_title' => $validated['jobTitle'],
            'description' => $validated['description'],
            'start_date' => $validated['startDate'],
            'end_date' => $validated['endDate'],
        ];

        if ($this->editingId) {
            $record = auth()->user()->candidateProfile->experienceRecords()->findOrFail($this->editingId);
            $this->authorize('update', $record);
            $record->update($data);
        } else {
            auth()->user()->candidateProfile->experienceRecords()->create($data);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('profile-updated');
    }

    public function delete(): void
    {
        $record = auth()->user()->candidateProfile->experienceRecords()->findOrFail($this->editingId);
        $this->authorize('delete', $record);
        $record->delete();

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
        $this->editorRound++;
        $this->editingId = null;
        $this->companyName = '';
        $this->jobTitle = '';
        $this->description = null;
        $this->startDate = '';
        $this->endDate = null;
        $this->resetValidation();
    }
}; ?>

<div>
    <x-profile.section id="experience" :heading="__('Experience')" :level="$level">
        {{-- The empty state below carries the same button. --}}
        @if ($this->records->isNotEmpty())
            <x-slot:actions>
                <flux:button wire:click="create" size="sm" icon="plus">{{ __('Add experience') }}</flux:button>
            </x-slot:actions>
        @endif

        @if ($this->records->isEmpty())
            <div class="px-5 pb-5 sm:px-6">
                <x-empty-state icon="briefcase" :level="$level + 1" :heading="__('No roles yet')">
                    {{ __('Add the jobs you have held. Companies you apply to read them alongside your CV.') }}

                    <x-slot:actions>
                        <flux:button wire:click="create" variant="primary" size="sm" icon="plus">{{ __('Add experience') }}</flux:button>
                        @if ($this->importableCv)
                            <flux:button :href="route('candidate.resume-import', $this->importableCv)" wire:navigate size="sm" icon="document-arrow-down">{{ __('Fill from your CV') }}</flux:button>
                        @endif
                    </x-slot:actions>
                </x-empty-state>
            </div>
        @else
            <ul class="divide-y divide-line border-t border-line">
                @foreach ($this->records as $record)
                    <x-profile.experience-item :record="$record" wire:key="experience-{{ $record->id }}">
                        <x-slot:actions>
                            <flux:button wire:click="edit({{ $record->id }})" variant="ghost" size="sm" icon="pencil" :aria-label="__('Edit :title', ['title' => $record->job_title])" />
                        </x-slot:actions>
                    </x-profile.experience-item>
                @endforeach
            </ul>
        @endif
    </x-profile.section>

    <flux:modal wire:model="showModal" class="w-full max-w-lg" @close="closeModal">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __('Edit experience') : __('Add experience') }}</flux:heading>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input wire:model="jobTitle" :label="__('Job title')" placeholder="Backend Developer" />
                <flux:input wire:model="companyName" :label="__('Company')" placeholder="Acme Inc." />
            </div>

            {{-- The editor ignores Livewire once drawn, so it is keyed by the
                 role being edited: opening another role draws a new editor
                 with that role's text instead of keeping the last one. --}}
            <div wire:key="description-{{ $editingId ?? 'new' }}-{{ $editorRound }}">
                <x-rich-text-editor wire="description" :value="$description" :label="__('Description')" :description="__('What did you work on? (optional)')" :headings="false" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input type="date" wire:model="startDate" :label="__('Start date')" />
                <flux:input type="date" wire:model="endDate" :label="__('End date')" :description="__('Leave empty if you still work here')" />
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2">
                @if ($editingId)
                    <flux:button wire:click="delete" wire:confirm="{{ __('Delete this role from your profile?') }}" variant="ghost" icon="trash">{{ __('Delete') }}</flux:button>
                @endif

                <div class="ms-auto flex gap-2">
                    <flux:button wire:click="closeModal" variant="ghost">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</div>
