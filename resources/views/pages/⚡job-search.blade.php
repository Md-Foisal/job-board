<?php

use App\Enums\EmploymentType;
use App\Enums\WorkplaceType;
use App\Livewire\Concerns\FiltersJobPostings;
use App\Models\Category;
use App\Models\Skill;
use App\Support\PublicCache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::guest')] #[Title('Job search')] class extends Component {
    use FiltersJobPostings, WithPagination;

    public function with(): array
    {
        $jobPostings = $this->filteredQuery()->paginate(20);

        return [
            'jobPostings' => $jobPostings,
            'matchScores' => $this->matchScores($jobPostings->getCollection()),
            // Re-read on every keystroke otherwise: the filters re-render
            // the whole component.
            'skills' => PublicCache::lookupModels('skills', Skill::class, fn () => Skill::orderBy('name')->get()),
            'categories' => PublicCache::lookupModels('categories', Category::class, fn () => Category::orderBy('name')->get()),
            'workplaceTypes' => WorkplaceType::cases(),
            'employmentTypes' => EmploymentType::cases(),
            'payCurrency' => $this->payCurrency(),
            'payCurrencies' => $this->payCurrencyOptions(),
            'canSortByMatch' => $this->canSortByMatch(),
            // Guests too: the link signs them in and brings them back
            // with the search intact. Employers have no alerts to keep.
            'canCreateAlert' => ! auth()->check() || auth()->user()->isCandidate(),
            'alertUrl' => route('candidate.job-alerts.index', ['create' => 1] + $this->criteria()),
        ];
    }
}; ?>

<div class="mx-auto max-w-6xl px-6 py-10">
    <h1 class="font-display text-2xl font-bold text-ink">Find your next role</h1>

    <div class="mt-6 grid grid-cols-1 gap-8 lg:grid-cols-[260px_1fr]">
        <aside class="space-y-5">
            <flux:input wire:model.live.debounce.400ms="q" placeholder="Job title..." icon="magnifying-glass" />

            <flux:select wire:model.live="skill" placeholder="Any skill">
                <flux:select.option value="">Any skill</flux:select.option>
                @foreach ($skills as $skillOption)
                    <flux:select.option value="{{ $skillOption->id }}">{{ $skillOption->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="category" placeholder="Any category">
                <flux:select.option value="">Any category</flux:select.option>
                @foreach ($categories as $categoryOption)
                    <flux:select.option value="{{ $categoryOption->id }}">{{ $categoryOption->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="workplaceType" placeholder="Any workplace type">
                <flux:select.option value="">Any workplace type</flux:select.option>
                @foreach ($workplaceTypes as $type)
                    <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="employmentType" placeholder="Any employment type">
                <flux:select.option value="">Any employment type</flux:select.option>
                @foreach ($employmentTypes as $type)
                    <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model.live.debounce.400ms="location" placeholder="City" />

            <flux:select wire:model.live="currency" :aria-label="__('Pay currency')">
                <flux:select.option value="">{{ __('Pay in any currency') }}</flux:select.option>
                @foreach ($payCurrencies as $code => $label)
                    <flux:select.option value="{{ $code }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            {{-- Amounts mean nothing until their currency is known, so they
                 are asked for only once one is chosen. --}}
            @if ($payCurrency)
                <div class="grid grid-cols-2 gap-2">
                    <flux:input wire:model.live.debounce.400ms="salaryMin" type="number" min="1" :placeholder="__('Min / month')" :aria-label="__('Minimum pay a month, in :currency', ['currency' => $payCurrency])" />
                    <flux:input wire:model.live.debounce.400ms="salaryMax" type="number" min="1" :placeholder="__('Max / month')" :aria-label="__('Maximum pay a month, in :currency', ['currency' => $payCurrency])" />
                </div>
            @endif

            <flux:input wire:model.live.debounce.400ms="experience" type="number" placeholder="Min years experience" />

            <flux:button wire:click="resetFilters" variant="ghost" size="sm">Clear filters</flux:button>

            @if ($canCreateAlert)
                <flux:button :href="$alertUrl" icon="bell" size="sm" class="w-full">{{ __('Create job alert') }}</flux:button>
            @endif
        </aside>

        <div>
            <div class="mb-4 flex items-center justify-between">
                <p class="text-sm text-ink-muted">
                    {{ $jobPostings->total() }} {{ \Illuminate\Support\Str::plural('opening', $jobPostings->total()) }}
                </p>

                <flux:select wire:model.live="sort" size="sm" :aria-label="__('Sort')">
                    <flux:select.option value="newest">{{ __('Newest') }}</flux:select.option>
                    @if ($canSortByMatch)
                        <flux:select.option value="match">{{ __('Best match') }}</flux:select.option>
                    @endif
                    @if ($payCurrency)
                        <flux:select.option value="salary_high">{{ __('Pay in :currency: high to low', ['currency' => $payCurrency]) }}</flux:select.option>
                        <flux:select.option value="salary_low">{{ __('Pay in :currency: low to high', ['currency' => $payCurrency]) }}</flux:select.option>
                    @endif
                </flux:select>
            </div>

            @if ($jobPostings->isEmpty())
                <x-empty-state icon="magnifying-glass" :heading="__('No openings match these filters yet')">
                    {{ __('Try widening your search.') }}
                </x-empty-state>
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @foreach ($jobPostings as $jobPosting)
                        <x-job-card :job-posting="$jobPosting" :match-score="$matchScores[$jobPosting->id] ?? null" wire:key="job-{{ $jobPosting->id }}" />
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $jobPostings->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
