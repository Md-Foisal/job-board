<?php

use Livewire\Attributes\Computed;
use Livewire\Component;

/*
 * The Projects section of the candidate's own profile, edited where it
 * is read like Experience. A software CV is read for these, each linked
 * to where it runs and where its code is.
 */
new class extends Component {
    public int $level = 2;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public ?string $description = null;

    public ?string $url = null;

    public ?string $sourceUrl = null;

    public ?string $startDate = null;

    public ?string $endDate = null;

    /**
     * Counts each opening of the dialog, so the editor, which ignores
     * Livewire once drawn, is drawn again with the right text.
     */
    public int $editorRound = 0;

    #[Computed]
    public function projects()
    {
        return auth()->user()->candidateProfile
            ->projects()
            ->newestFirst()
            ->get();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $project = auth()->user()->candidateProfile->projects()->findOrFail($id);
        $this->authorize('update', $project);

        $this->resetValidation();
        $this->editorRound++;
        $this->editingId = $project->id;
        $this->name = $project->name;
        $this->description = $project->description;
        $this->url = $project->url;
        $this->sourceUrl = $project->source_url;
        $this->startDate = $project->start_date?->format('Y-m-d');
        $this->endDate = $project->end_date?->format('Y-m-d');

        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'url' => ['nullable', 'url:http,https', 'max:255'],
            'sourceUrl' => ['nullable', 'url:http,https', 'max:255'],
            'startDate' => ['nullable', 'date', 'required_with:endDate'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
        ], [], [
            'url' => __('live link'),
            'sourceUrl' => __('code link'),
        ]);

        $data = [
            'name' => $validated['name'],
            'description' => $validated['description'],
            'url' => $validated['url'],
            'source_url' => $validated['sourceUrl'],
            'start_date' => $validated['startDate'],
            'end_date' => $validated['endDate'],
        ];

        if ($this->editingId) {
            $project = auth()->user()->candidateProfile->projects()->findOrFail($this->editingId);
            $this->authorize('update', $project);
            $project->update($data);
        } else {
            auth()->user()->candidateProfile->projects()->create($data);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('profile-updated');
    }

    public function delete(): void
    {
        $project = auth()->user()->candidateProfile->projects()->findOrFail($this->editingId);
        $this->authorize('delete', $project);
        $project->delete();

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
        $this->name = '';
        $this->description = null;
        $this->url = null;
        $this->sourceUrl = null;
        $this->startDate = null;
        $this->endDate = null;
        $this->resetValidation();
    }
}; ?>

<div>
    <x-profile.section id="projects" :heading="__('Projects')" :level="$level">
        {{-- The empty state below carries the same button. --}}
        @if ($this->projects->isNotEmpty())
            <x-slot:actions>
                <flux:button wire:click="create" size="sm" icon="plus">{{ __('Add project') }}</flux:button>
            </x-slot:actions>
        @endif

        @if ($this->projects->isEmpty())
            <div class="px-5 pb-5 sm:px-6">
                <x-empty-state icon="rocket-launch" :level="$level + 1" :heading="__('No projects yet')">
                    {{ __('Things you built, with a link to see them and to their code.') }}

                    <x-slot:actions>
                        <flux:button wire:click="create" variant="primary" size="sm" icon="plus">{{ __('Add project') }}</flux:button>
                    </x-slot:actions>
                </x-empty-state>
            </div>
        @else
            <ul class="divide-y divide-line border-t border-line">
                @foreach ($this->projects as $project)
                    <x-profile.project-item :project="$project" wire:key="project-{{ $project->id }}">
                        <x-slot:actions>
                            <flux:button wire:click="edit({{ $project->id }})" variant="ghost" size="sm" icon="pencil" :aria-label="__('Edit :title', ['title' => $project->name])" />
                        </x-slot:actions>
                    </x-profile.project-item>
                @endforeach
            </ul>
        @endif
    </x-profile.section>

    <flux:modal wire:model="showModal" class="w-full max-w-lg" @close="closeModal">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __('Edit project') : __('Add project') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Project name')" placeholder="Job Board" />

            {{-- Keyed by the project and the opening, so another project's
                 text never stays in the editor. --}}
            <div wire:key="project-description-{{ $editingId ?? 'new' }}-{{ $editorRound }}">
                <x-rich-text-editor wire="description" :value="$description" :label="__('Description')" :description="__('What it does and what you did on it (optional)')" :headings="false" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input type="url" wire:model="url" :label="__('Live link')" placeholder="https://" :description="__('Optional')" />
                <flux:input type="url" wire:model="sourceUrl" :label="__('Code link')" placeholder="https://github.com/…" :description="__('Optional')" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input type="date" wire:model="startDate" :label="__('Start date')" :description="__('Optional')" />
                <flux:input type="date" wire:model="endDate" :label="__('End date')" :description="__('Leave empty if ongoing')" />
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2">
                @if ($editingId)
                    <flux:button wire:click="delete" wire:confirm="{{ __('Delete this project from your profile?') }}" variant="ghost" icon="trash">{{ __('Delete') }}</flux:button>
                @endif

                <div class="ms-auto flex gap-2">
                    <flux:button wire:click="closeModal" variant="ghost">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</div>
