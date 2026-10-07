<?php

use App\Models\Document;
use Livewire\Attributes\Computed;
use Livewire\Component;

/*
 * The Education section of the candidate's own profile, edited where it
 * is read, the same way as Experience. Used on the profile page and on
 * the Education page, which is the same section on a page of its own.
 */
new class extends Component {
    public int $level = 2;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $institutionName = '';

    public ?string $degree = null;

    public ?string $fieldOfStudy = null;

    public string $startDate = '';

    public ?string $endDate = null;

    #[Computed]
    public function records()
    {
        return auth()->user()->candidateProfile
            ->educationRecords()
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
        $record = auth()->user()->candidateProfile->educationRecords()->findOrFail($id);
        $this->authorize('update', $record);

        $this->resetValidation();
        $this->editingId = $record->id;
        $this->institutionName = $record->institution_name;
        $this->degree = $record->degree;
        $this->fieldOfStudy = $record->field_of_study;
        $this->startDate = $record->start_date->format('Y-m-d');
        $this->endDate = $record->end_date?->format('Y-m-d');

        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'institutionName' => ['required', 'string', 'max:255'],
            'degree' => ['nullable', 'string', 'max:255'],
            'fieldOfStudy' => ['nullable', 'string', 'max:255'],
            'startDate' => ['required', 'date'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
        ]);

        $data = [
            'institution_name' => $validated['institutionName'],
            'degree' => $validated['degree'],
            'field_of_study' => $validated['fieldOfStudy'],
            'start_date' => $validated['startDate'],
            'end_date' => $validated['endDate'],
        ];

        if ($this->editingId) {
            $record = auth()->user()->candidateProfile->educationRecords()->findOrFail($this->editingId);
            $this->authorize('update', $record);
            $record->update($data);
        } else {
            auth()->user()->candidateProfile->educationRecords()->create($data);
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(): void
    {
        $record = auth()->user()->candidateProfile->educationRecords()->findOrFail($this->editingId);
        $this->authorize('delete', $record);
        $record->delete();

        $this->showModal = false;
        $this->resetForm();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->institutionName = '';
        $this->degree = null;
        $this->fieldOfStudy = null;
        $this->startDate = '';
        $this->endDate = null;
        $this->resetValidation();
    }
}; ?>

<div>
    <x-profile.section id="education" :heading="__('Education')" :level="$level">
        {{-- The empty state below carries the same button. --}}
        @if ($this->records->isNotEmpty())
            <x-slot:actions>
                <flux:button wire:click="create" size="sm" icon="plus">{{ __('Add education') }}</flux:button>
            </x-slot:actions>
        @endif

        @if ($this->records->isEmpty())
            <div class="px-5 pb-5 sm:px-6">
                <x-empty-state icon="academic-cap" :level="$level + 1" :heading="__('No education yet')">
                    {{ __('Add schools, degrees and courses, newest first.') }}

                    <x-slot:actions>
                        <flux:button wire:click="create" variant="primary" size="sm" icon="plus">{{ __('Add education') }}</flux:button>
                        @if ($this->importableCv)
                            <flux:button :href="route('candidate.resume-import', $this->importableCv)" wire:navigate size="sm" icon="document-arrow-down">{{ __('Fill from your CV') }}</flux:button>
                        @endif
                    </x-slot:actions>
                </x-empty-state>
            </div>
        @else
            <ul class="divide-y divide-line border-t border-line">
                @foreach ($this->records as $record)
                    <x-profile.education-item :record="$record" wire:key="education-{{ $record->id }}">
                        <x-slot:actions>
                            <flux:button wire:click="edit({{ $record->id }})" variant="ghost" size="sm" icon="pencil" :aria-label="__('Edit :title', ['title' => $record->institution_name])" />
                        </x-slot:actions>
                    </x-profile.education-item>
                @endforeach
            </ul>
        @endif
    </x-profile.section>

    <flux:modal wire:model="showModal" class="w-full max-w-lg" @close="closeModal">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __('Edit education') : __('Add education') }}</flux:heading>

            <flux:input wire:model="institutionName" :label="__('School or institution')" />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input wire:model="degree" :label="__('Degree')" placeholder="B.Sc." :description="__('Optional')" />
                <flux:input wire:model="fieldOfStudy" :label="__('Field of study')" placeholder="Computer Science" :description="__('Optional')" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input type="date" wire:model="startDate" :label="__('Start date')" />
                <flux:input type="date" wire:model="endDate" :label="__('End date')" :description="__('Leave empty if you are still studying')" />
            </div>

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
