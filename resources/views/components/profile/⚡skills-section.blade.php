<?php

use App\Enums\ProficiencyLevel;
use App\Models\Document;
use App\Models\Skill;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/*
 * The Skills section of the candidate's own profile. Job matches are
 * worked out from these, so adding one is kept short: search, pick, and
 * it is on the profile at the level chosen above the results; the dialog
 * stays open for the next one. A skill's level is changed, or the skill
 * removed, from its own small dialog.
 */
new class extends Component {
    public int $level = 2;

    public bool $adding = false;

    public string $q = '';

    public string $newLevel = 'intermediate';

    public bool $editingOpen = false;

    public ?int $editingId = null;

    public string $editingLevel = 'intermediate';

    #[Computed]
    public function skills(): Collection
    {
        return auth()->user()->candidateProfile->skills()->orderBy('name')->get();
    }

    #[Computed]
    public function importableCv(): ?Document
    {
        return $this->skills->isEmpty() ? auth()->user()->candidateProfile->importableCv() : null;
    }

    /**
     * Live matches for the search box, two characters on, without the
     * skills already on the profile.
     */
    #[Computed]
    public function results(): Collection
    {
        if (mb_strlen(trim($this->q)) < 2) {
            return collect();
        }

        return Skill::query()
            ->search(trim($this->q))
            ->whereNotIn('id', $this->skills->pluck('id'))
            ->orderBy('name')
            ->limit(6)
            ->get();
    }

    #[Computed]
    public function editing(): ?Skill
    {
        return $this->editingId ? $this->skills->firstWhere('id', $this->editingId) : null;
    }

    public function startAdding(): void
    {
        $this->q = '';
        $this->newLevel = ProficiencyLevel::Intermediate->value;
        $this->resetValidation();
        $this->adding = true;
    }

    public function add(int $skillId): void
    {
        $this->validate(['newLevel' => ['required', Rule::enum(ProficiencyLevel::class)]]);

        $skill = Skill::query()->findOrFail($skillId);

        auth()->user()->candidateProfile->skills()->syncWithoutDetaching([
            $skill->id => ['proficiency' => $this->newLevel],
        ]);

        $this->q = '';
        unset($this->skills, $this->results);
    }

    public function edit(int $skillId): void
    {
        $skill = $this->skills->firstWhere('id', $skillId);

        abort_if($skill === null, 404);

        $this->resetValidation();
        $this->editingId = $skill->id;
        $this->editingLevel = $skill->pivot->proficiency->value;
        $this->editingOpen = true;
    }

    public function saveLevel(): void
    {
        $this->validate(['editingLevel' => ['required', Rule::enum(ProficiencyLevel::class)]]);

        if ($this->editing !== null) {
            auth()->user()->candidateProfile->skills()->updateExistingPivot($this->editingId, ['proficiency' => $this->editingLevel]);
        }

        $this->stopEditing();
    }

    public function remove(): void
    {
        if ($this->editingId !== null) {
            auth()->user()->candidateProfile->skills()->detach($this->editingId);
        }

        $this->stopEditing();
    }

    public function stopEditing(): void
    {
        $this->editingOpen = false;
        $this->editingId = null;
        unset($this->skills, $this->editing);
    }
}; ?>

<div>
    <x-profile.section id="skills" :heading="__('Skills')" :level="$level">
        {{-- The empty state below carries the same button. --}}
        @if ($this->skills->isNotEmpty())
            <x-slot:actions>
                <flux:button wire:click="startAdding" size="sm" icon="plus">{{ __('Add skills') }}</flux:button>
            </x-slot:actions>
        @endif

        @if ($this->skills->isEmpty())
            <div class="px-5 pb-5 sm:px-6">
                <x-empty-state icon="tag" :level="$level + 1" :heading="__('No skills yet')">
                    {{ __('Job matches are worked out from your skills, so without them no job can show how well it fits you.') }}

                    <x-slot:actions>
                        <flux:button wire:click="startAdding" variant="primary" size="sm" icon="plus">{{ __('Add skills') }}</flux:button>
                        @if ($this->importableCv)
                            <flux:button :href="route('candidate.resume-import', $this->importableCv)" wire:navigate size="sm" icon="document-arrow-down">{{ __('Fill from your CV') }}</flux:button>
                        @endif
                    </x-slot:actions>
                </x-empty-state>
            </div>
        @else
            <div class="border-t border-line px-5 py-5 sm:px-6">
                <ul class="flex flex-wrap gap-2">
                    @foreach ($this->skills as $skill)
                        <li wire:key="skill-{{ $skill->id }}">
                            <button
                                type="button"
                                wire:click="edit({{ $skill->id }})"
                                class="rounded-full transition hover:-translate-y-px focus-visible:outline-2 focus-visible:outline-offset-2"
                                aria-label="{{ __('Change level or remove :skill', ['skill' => $skill->name]) }}"
                            >
                                <x-profile.skill-chip :skill="$skill" />
                            </button>
                        </li>
                    @endforeach
                </ul>
                <flux:text size="sm" class="mt-3">{{ __('Select a skill to change its level or remove it.') }}</flux:text>
            </div>
        @endif
    </x-profile.section>

    {{-- Adding: the level is picked first, then each result clicked goes
         on at that level. --}}
    <flux:modal wire:model="adding" class="w-full max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add skills') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Search, then pick a skill to add it at the level chosen here.') }}</flux:text>
            </div>

            <flux:radio.group wire:model="newLevel" variant="segmented" :label="__('Level')">
                @foreach (ProficiencyLevel::cases() as $proficiency)
                    <flux:radio :value="$proficiency->value" :label="$proficiency->label()" />
                @endforeach
            </flux:radio.group>

            <div>
                <flux:input
                    type="search"
                    wire:model.live.debounce.300ms="q"
                    autofocus
                    :placeholder="__('Search skills, e.g. Laravel, Figma…')"
                    :aria-label="__('Search skills')"
                    icon="magnifying-glass"
                    autocomplete="off"
                />

                <div class="mt-2" aria-live="polite">
                    <p wire:loading.delay wire:target="q" class="px-1 py-2 text-sm text-ink-muted">{{ __('Searching…') }}</p>

                    <div wire:loading.remove.delay wire:target="q">
                        @if (mb_strlen(trim($q)) >= 2)
                            @forelse ($this->results as $result)
                                <button
                                    type="button"
                                    wire:key="result-{{ $result->id }}"
                                    wire:click="add({{ $result->id }})"
                                    class="flex w-full items-center justify-between gap-2 rounded-control px-3 py-2.5 text-left text-sm transition hover:bg-surface"
                                >
                                    <span class="text-ink">{{ $result->name }}</span>
                                    <flux:icon.plus variant="micro" class="text-ink-muted" aria-hidden="true" />
                                </button>
                            @empty
                                <p class="px-1 py-2 text-sm text-ink-muted">{{ __('No matching skills.') }}</p>
                            @endforelse
                        @endif
                    </div>
                </div>
            </div>

            @if ($this->skills->isNotEmpty())
                <div>
                    <flux:text size="sm" class="font-medium">{{ __('On your profile') }}</flux:text>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($this->skills as $skill)
                            <x-profile.skill-chip :skill="$skill" wire:key="added-{{ $skill->id }}" />
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="primary">{{ __('Done') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model="editingOpen" @close="stopEditing" class="w-full max-w-md">
        @if ($this->editing)
            <form wire:submit="saveLevel" class="space-y-6">
                <flux:heading size="lg">{{ $this->editing->name }}</flux:heading>

                <flux:radio.group wire:model="editingLevel" variant="segmented" :label="__('Level')">
                    @foreach (ProficiencyLevel::cases() as $proficiency)
                        <flux:radio :value="$proficiency->value" :label="$proficiency->label()" />
                    @endforeach
                </flux:radio.group>

                <div class="flex flex-wrap items-center justify-between gap-2">
                    <flux:button wire:click="remove" variant="ghost" icon="trash">{{ __('Remove') }}</flux:button>

                    <div class="flex gap-2">
                        <flux:button wire:click="stopEditing" variant="ghost">{{ __('Cancel') }}</flux:button>
                        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    </div>
                </div>
            </form>
        @endif
    </flux:modal>
</div>
