<?php

use Livewire\Attributes\Computed;
use Livewire\Component;

/*
 * The Publications section of the candidate's own profile: articles,
 * papers and books, with where and when each came out and a link a
 * reader can follow.
 */
new class extends Component {
    public int $level = 2;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $title = '';

    public ?string $publisher = null;

    public ?string $publishedOn = null;

    public ?string $url = null;

    public ?string $description = null;

    #[Computed]
    public function publications()
    {
        return auth()->user()->candidateProfile->publications()->newestFirst()->get();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $publication = auth()->user()->candidateProfile->publications()->findOrFail($id);
        $this->authorize('update', $publication);

        $this->resetValidation();
        $this->editingId = $publication->id;
        $this->title = $publication->title;
        $this->publisher = $publication->publisher;
        $this->publishedOn = $publication->published_on?->format('Y-m-d');
        $this->url = $publication->url;
        $this->description = $publication->description;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'publishedOn' => ['nullable', 'date', 'before_or_equal:today'],
            'url' => ['nullable', 'url:http,https', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'publishedOn' => __('date'),
            'url' => __('link'),
        ]);

        $data = [
            'title' => $validated['title'],
            'publisher' => $validated['publisher'],
            'published_on' => $validated['publishedOn'],
            'url' => $validated['url'],
            'description' => $validated['description'],
        ];
        $profile = auth()->user()->candidateProfile;

        if ($this->editingId) {
            $publication = $profile->publications()->findOrFail($this->editingId);
            $this->authorize('update', $publication);
            $publication->update($data);
        } else {
            $profile->publications()->create($data);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('profile-updated');
    }

    public function delete(): void
    {
        $publication = auth()->user()->candidateProfile->publications()->findOrFail($this->editingId);
        $this->authorize('delete', $publication);
        $publication->delete();

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
        $this->publisher = null;
        $this->publishedOn = null;
        $this->url = null;
        $this->description = null;
        $this->resetValidation();
    }
}; ?>

<div>
    <x-profile.section id="publications" :heading="__('Publications')" :level="$level">
        @if ($this->publications->isNotEmpty())
            <x-slot:actions>
                <flux:button wire:click="create" size="sm" icon="plus">{{ __('Add publication') }}</flux:button>
            </x-slot:actions>
        @endif

        @if ($this->publications->isEmpty())
            <div class="px-5 pb-5 sm:px-6">
                <x-empty-state icon="newspaper" :level="$level + 1" :heading="__('No publications yet')">
                    {{ __('Articles, papers or books you wrote, with a link to read them.') }}

                    <x-slot:actions>
                        <flux:button wire:click="create" variant="primary" size="sm" icon="plus">{{ __('Add publication') }}</flux:button>
                    </x-slot:actions>
                </x-empty-state>
            </div>
        @else
            <ul class="divide-y divide-line border-t border-line">
                @foreach ($this->publications as $publication)
                    <x-profile.entry-item icon="newspaper" :title="$publication->title" :subtitle="$publication->publisher" :meta="$publication->published_on?->format(\App\Support\DateFormat::MONTH)" :note="$publication->description" :link="$publication->url" wire:key="publication-{{ $publication->id }}">
                        <x-slot:actions>
                            <flux:button wire:click="edit({{ $publication->id }})" variant="ghost" size="sm" icon="pencil" :aria-label="__('Edit :title', ['title' => $publication->title])" />
                        </x-slot:actions>
                    </x-profile.entry-item>
                @endforeach
            </ul>
        @endif
    </x-profile.section>

    <flux:modal wire:model="showModal" class="w-full max-w-lg" @close="closeModal">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __('Edit publication') : __('Add publication') }}</flux:heading>

            <flux:input wire:model="title" :label="__('Title')" />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input wire:model="publisher" :label="__('Published in or by')" :description="__('Optional')" />
                <flux:input type="date" wire:model="publishedOn" :label="__('Date')" :description="__('Optional')" />
            </div>

            <flux:input type="url" wire:model="url" :label="__('Link')" placeholder="https://" :description="__('Optional')" />
            <flux:textarea wire:model="description" :label="__('About it')" :description="__('Optional')" rows="3" maxlength="1000" />

            <div class="flex flex-wrap items-center justify-between gap-2">
                @if ($editingId)
                    <flux:button wire:click="delete" wire:confirm="{{ __('Delete this publication from your profile?') }}" variant="ghost" icon="trash">{{ __('Delete') }}</flux:button>
                @endif

                <div class="ms-auto flex gap-2">
                    <flux:button wire:click="closeModal" variant="ghost">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</div>
