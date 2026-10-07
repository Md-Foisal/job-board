<?php

use App\Models\EducationRecord;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Education')] class extends Component {
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $institutionName = '';

    public ?string $degree = null;

    public ?string $fieldOfStudy = null;

    public string $startDate = '';

    public ?string $endDate = null;

    public function with(): array
    {
        return [
            'educationRecords' => auth()->user()->candidateProfile
                ->educationRecords()
                ->orderByDesc('start_date')
                ->get(),
        ];
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

    public function delete(int $id): void
    {
        $record = auth()->user()->candidateProfile->educationRecords()->findOrFail($id);
        $this->authorize('delete', $record);
        $record->delete();
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

<x-page>
    <x-page-header :title="__('Education')" :description="__('Schools, degrees and what you studied. The companies you apply to see these.')">
        <x-slot:actions>
            <flux:button wire:click="create" variant="primary" icon="plus">{{ __('Add education') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="space-y-4">
        @forelse ($educationRecords as $record)
            <x-card wire:key="education-{{ $record->id }}" class="flex items-start justify-between gap-4">
                <div class="flex gap-4">
                    <x-icon-tile icon="academic-cap" />

                    <div>
                        <p class="font-medium text-ink">{{ $record->institution_name }}</p>

                        @if ($record->degree || $record->field_of_study)
                            <p class="text-sm text-ink-muted">
                                {{ collect([$record->degree, $record->field_of_study])->filter()->join(', ') }}
                            </p>
                        @endif

                        <p class="mt-1 text-sm text-ink-muted">
                            {{ $record->start_date->format(\App\Support\DateFormat::MONTH) }} &mdash; {{ $record->end_date?->format(\App\Support\DateFormat::MONTH) ?? __('Present') }}
                        </p>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <flux:button wire:click="edit({{ $record->id }})" variant="ghost" size="sm" icon="pencil" :aria-label="__('Edit')" />
                    <flux:button wire:click="delete({{ $record->id }})" wire:confirm="{{ __('Remove this education record?') }}" variant="ghost" size="sm" icon="trash" :aria-label="__('Remove')" />
                </div>
            </x-card>
        @empty
            <x-empty-state icon="academic-cap" :heading="__('You haven\'t added any education yet.')">
                <x-slot:actions>
                    <flux:button wire:click="create" size="sm" icon="plus">{{ __('Add education') }}</flux:button>
                </x-slot:actions>
            </x-empty-state>
        @endforelse
    </div>

    <flux:modal wire:model="showModal" class="max-w-lg" @close="closeModal">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __('Edit education') : __('Add education') }}</flux:heading>

            <flux:input wire:model="institutionName" :label="__('School or institution')" />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input wire:model="degree" :label="__('Degree')" placeholder="B.Sc." />
                <flux:input wire:model="fieldOfStudy" :label="__('Field of study')" placeholder="Computer Science" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input type="date" wire:model="startDate" :label="__('Start date')" />
                <flux:input type="date" wire:model="endDate" :label="__('End date')" :description="__('Leave empty if ongoing')" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="closeModal" variant="ghost">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</x-page>
