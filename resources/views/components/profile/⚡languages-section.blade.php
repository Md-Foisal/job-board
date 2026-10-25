<?php

use App\Enums\LanguageLevel;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/*
 * The Languages section of the candidate's own profile: each language
 * with a level on the European scale (A1 to C2) or "native", the way
 * Europass and LinkedIn record it.
 */
new class extends Component {
    public int $level = 2;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $proficiency = 'b2';

    #[Computed]
    public function languages()
    {
        return auth()->user()->candidateProfile->languages()->get()
            ->sortBy([
                fn ($a, $b) => $b->level->rank() <=> $a->level->rank(),
                fn ($a, $b) => strnatcasecmp($a->name, $b->name),
            ])
            ->values();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $language = auth()->user()->candidateProfile->languages()->findOrFail($id);
        $this->authorize('update', $language);

        $this->resetValidation();
        $this->editingId = $language->id;
        $this->name = $language->name;
        $this->proficiency = $language->level->value;
        $this->showModal = true;
    }

    public function save(): void
    {
        $profile = auth()->user()->candidateProfile;

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('candidate_languages', 'name')->where('candidate_profile_id', $profile->id)->ignore($this->editingId)],
            'proficiency' => ['required', Rule::enum(LanguageLevel::class)],
        ], [
            'name.unique' => __('This language is already on your profile.'),
        ], [
            'name' => __('language'),
            'proficiency' => __('level'),
        ]);

        $data = ['name' => trim($validated['name']), 'level' => $validated['proficiency']];

        if ($this->editingId) {
            $language = $profile->languages()->findOrFail($this->editingId);
            $this->authorize('update', $language);
            $language->update($data);
        } else {
            $profile->languages()->create($data);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('profile-updated');
    }

    public function delete(): void
    {
        $language = auth()->user()->candidateProfile->languages()->findOrFail($this->editingId);
        $this->authorize('delete', $language);
        $language->delete();

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
        $this->name = '';
        $this->proficiency = 'b2';
        $this->resetValidation();
    }
}; ?>

<div>
    <x-profile.section id="languages" :heading="__('Languages')" :level="$level">
        @if ($this->languages->isNotEmpty())
            <x-slot:actions>
                <flux:button wire:click="create" size="sm" icon="plus">{{ __('Add language') }}</flux:button>
            </x-slot:actions>
        @endif

        @if ($this->languages->isEmpty())
            <div class="px-5 pb-5 sm:px-6">
                <x-empty-state icon="language" :level="$level + 1" :heading="__('No languages yet')">
                    {{ __('The languages you can work in, and how well. Employers abroad read this first.') }}

                    <x-slot:actions>
                        <flux:button wire:click="create" variant="primary" size="sm" icon="plus">{{ __('Add language') }}</flux:button>
                    </x-slot:actions>
                </x-empty-state>
            </div>
        @else
            <ul class="divide-y divide-line border-t border-line">
                @foreach ($this->languages as $language)
                    <x-profile.entry-item icon="language" :title="$language->name" :meta="__($language->level->label())" wire:key="language-{{ $language->id }}">
                        <x-slot:actions>
                            <flux:button wire:click="edit({{ $language->id }})" variant="ghost" size="sm" icon="pencil" :aria-label="__('Edit :title', ['title' => $language->name])" />
                        </x-slot:actions>
                    </x-profile.entry-item>
                @endforeach
            </ul>
        @endif
    </x-profile.section>

    <flux:modal wire:model="showModal" class="w-full max-w-lg" @close="closeModal">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __('Edit language') : __('Add language') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Language')" placeholder="English" maxlength="60" />

            <flux:select wire:model="proficiency" :label="__('Level')" :description="__('The European scale most employers read: A1 is a beginner, C2 close to a native speaker.')">
                @foreach (\App\Enums\LanguageLevel::cases() as $option)
                    <flux:select.option :value="$option->value">{{ __($option->label()) }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex flex-wrap items-center justify-between gap-2">
                @if ($editingId)
                    <flux:button wire:click="delete" wire:confirm="{{ __('Delete this language from your profile?') }}" variant="ghost" icon="trash">{{ __('Delete') }}</flux:button>
                @endif

                <div class="ms-auto flex gap-2">
                    <flux:button wire:click="closeModal" variant="ghost">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</div>
