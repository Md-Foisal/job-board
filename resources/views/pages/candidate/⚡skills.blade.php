<?php

use App\Enums\ProficiencyLevel;
use App\Models\Skill;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Skills')] class extends Component {
    public string $q = '';

    /**
     * skill_id => proficiency value. Built up locally as the candidate
     * searches/adds/removes/re-rates skills, then written to the pivot
     * table in one go on Save. No Action class is needed here: it is a
     * single belongsToMany relation and one sync() call.
     *
     * @var array<int, string>
     */
    public array $selected = [];

    public function mount(): void
    {
        $this->selected = auth()->user()->candidateProfile
            ->skills()
            ->get()
            ->mapWithKeys(fn (Skill $skill) => [$skill->id => $skill->pivot->proficiency->value])
            ->all();
    }

    /**
     * Live-as-you-type matches for the search box -- same shape as
     * JobSearchAutocomplete::getResultsProperty() (2-character floor,
     * capped result count), minus whatever is already selected so a
     * candidate can't add the same skill twice.
     */
    #[Computed]
    public function results(): Collection
    {
        if (mb_strlen(trim($this->q)) < 2) {
            return collect();
        }

        return Skill::query()
            ->search($this->q)
            ->whereNotIn('id', array_keys($this->selected))
            ->orderBy('name')
            ->limit(6)
            ->get();
    }

    #[Computed]
    public function selectedSkills(): Collection
    {
        if ($this->selected === []) {
            return collect();
        }

        return Skill::query()->whereIn('id', array_keys($this->selected))->orderBy('name')->get();
    }

    public function add(int $skillId): void
    {
        if (! isset($this->selected[$skillId])) {
            $this->selected[$skillId] = ProficiencyLevel::Intermediate->value;
        }

        $this->q = '';
    }

    public function remove(int $skillId): void
    {
        unset($this->selected[$skillId]);
    }

    public function save(): void
    {
        $this->validate([
            'selected.*' => ['required', Rule::enum(ProficiencyLevel::class)],
        ]);

        $sync = collect($this->selected)
            ->mapWithKeys(fn (string $proficiency, int $skillId) => [$skillId => ['proficiency' => $proficiency]])
            ->all();

        auth()->user()->candidateProfile->skills()->sync($sync);

        Flux::toast(variant: 'success', text: __('Skills updated.'));
    }
}; ?>

<x-page width="narrow">
    <x-page-header :title="__('Skills')" :description="__('Job matches are worked out from these, and the companies you apply to see them.')" />

    <div>
        <div
            x-data="{ open: false }"
            x-on:click.outside="open = false"
            x-on:keydown.escape.window="open = false"
            class="relative"
        >
            <flux:input
                type="search"
                wire:model.live.debounce.400ms="q"
                x-on:input="open = $event.target.value.trim().length > 0"
                x-on:focus="open = $event.target.value.trim().length > 0"
                placeholder="{{ __('Search skills, e.g. Laravel, Figma...') }}"
                aria-label="{{ __('Search skills') }}"
                icon="magnifying-glass"
                autocomplete="off"
            />

            <div
                x-show="open"
                x-cloak
                class="absolute z-20 mt-2 w-full overflow-hidden rounded-control border border-line bg-canvas text-left shadow-lift"
            >
                <div wire:loading.delay wire:target="q" class="px-4 py-3 text-sm text-ink-muted">
                    {{ __('Searching…') }}
                </div>

                <div wire:loading.remove.delay wire:target="q">
                    @if (mb_strlen(trim($q)) >= 2)
                        @forelse ($this->results as $skill)
                            <button
                                type="button"
                                wire:click="add({{ $skill->id }})"
                                x-on:click="open = false"
                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm transition hover:bg-surface"
                        >
                            <flux:icon name="tag" variant="micro" class="text-ink-muted" />
                            <span class="text-ink">{{ $skill->name }}</span>
                        </button>
                        @empty
                            <p class="px-4 py-3 text-sm text-ink-muted">
                                {{ __('No matching skills.') }}
                        </p>
                    @endforelse
                @endif
            </div>
        </div>
        </div>

        <div class="mt-6 space-y-3">
            @forelse ($this->selectedSkills as $skill)
                <x-card padding="sm" wire:key="skill-{{ $skill->id }}" class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <x-icon-tile icon="tag" size="sm" />
                        <p class="font-medium text-ink">{{ $skill->name }}</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:select wire:model="selected.{{ $skill->id }}" size="sm" class="w-40">
                            @foreach (\App\Enums\ProficiencyLevel::cases() as $level)
                                <flux:select.option value="{{ $level->value }}">{{ $level->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>

                        <flux:button wire:click="remove({{ $skill->id }})" variant="ghost" size="sm" icon="trash" :aria-label="__('Remove')" />
                    </div>
                </x-card>
            @empty
                <x-empty-state icon="tag" :heading="__('No skills added yet')">
                    {{ __('Search above to add one.') }}
                </x-empty-state>
            @endforelse
        </div>

        <div class="mt-6 flex justify-end">
            <flux:button wire:click="save" variant="primary">{{ __('Save') }}</flux:button>
        </div>
    </div>
</x-page>
