<?php

use App\Models\ExperienceRecord;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Experience')] class extends Component {
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $companyName = '';

    public string $jobTitle = '';

    public ?string $description = null;

    public string $startDate = '';

    public ?string $endDate = null;

    public function with(): array
    {
        return [
            'experienceRecords' => auth()->user()->candidateProfile
                ->experienceRecords()
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
        $record = auth()->user()->candidateProfile->experienceRecords()->findOrFail($id);
        $this->authorize('update', $record);

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
    }

    public function delete(int $id): void
    {
        $record = auth()->user()->candidateProfile->experienceRecords()->findOrFail($id);
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
        $this->companyName = '';
        $this->jobTitle = '';
        $this->description = null;
        $this->startDate = '';
        $this->endDate = null;
        $this->resetValidation();
    }
}; ?>

<div class="mx-auto max-w-2xl px-6 py-10">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Experience') }}</flux:heading>
            <flux:subheading>{{ __('Roles you have held — shown on your public profile.') }}</flux:subheading>
        </div>

        <flux:button wire:click="create" variant="primary" icon="plus">{{ __('Add') }}</flux:button>
    </div>

    <div class="mt-6 space-y-4">
        @forelse ($experienceRecords as $record)
            <x-card wire:key="experience-{{ $record->id }}" class="flex items-start justify-between gap-4">
                <div class="flex gap-4">
                    <x-icon-tile icon="briefcase" />

                    <div>
                        <p class="font-medium text-ink">{{ $record->job_title }}</p>
                        <p class="text-sm text-ink-muted">{{ $record->company_name }}</p>

                        <p class="mt-1 text-sm text-ink-muted">
                            {{ $record->start_date->format(\App\Support\DateFormat::MONTH) }} &mdash; {{ $record->end_date?->format(\App\Support\DateFormat::MONTH) ?? __('Present') }}
                        </p>

                        @if ($record->description)
                            <div class="prose-content mt-2">{!! $record->description !!}</div>
                        @endif
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <flux:button wire:click="edit({{ $record->id }})" variant="ghost" size="sm" icon="pencil" :aria-label="__('Edit')" />
                    <flux:button wire:click="delete({{ $record->id }})" wire:confirm="{{ __('Remove this experience record?') }}" variant="ghost" size="sm" icon="trash" :aria-label="__('Remove')" />
                </div>
            </x-card>
        @empty
            <x-empty-state icon="briefcase" :heading="__('You haven\'t added any experience yet.')">
                <x-slot:actions>
                    <flux:button wire:click="create" size="sm" icon="plus">{{ __('Add experience') }}</flux:button>
                </x-slot:actions>
            </x-empty-state>
        @endforelse
    </div>

    <flux:modal wire:model="showModal" class="max-w-lg" @close="closeModal">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __('Edit experience') : __('Add experience') }}</flux:heading>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input wire:model="jobTitle" :label="__('Job title')" placeholder="Backend Developer" />
                <flux:input wire:model="companyName" :label="__('Company')" placeholder="Acme Inc." />
            </div>

            <x-rich-text-editor wire="description" :value="$description" :label="__('Description')" :description="__('What did you work on? (optional)')" :headings="false" />

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
</div>
