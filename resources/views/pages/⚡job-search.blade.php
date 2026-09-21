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
            'skills' => PublicCache::lookup('skills', fn () => Skill::orderBy('name')->get()),
            'categories' => PublicCache::lookup('categories', fn () => Category::orderBy('name')->get()),
            'workplaceTypes' => WorkplaceType::cases(),
            'employmentTypes' => EmploymentType::cases(),
        ];
    }
}; ?>

<div class="mx-auto max-w-6xl px-6 py-10">
    <h1 class="font-display text-2xl font-bold text-zinc-900 dark:text-zinc-50">Find your next role</h1>

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

            <div class="grid grid-cols-2 gap-2">
                <flux:input wire:model.live.debounce.400ms="salaryMin" type="number" placeholder="Min salary" />
                <flux:input wire:model.live.debounce.400ms="salaryMax" type="number" placeholder="Max salary" />
            </div>

            <flux:input wire:model.live.debounce.400ms="experience" type="number" placeholder="Min years experience" />

            <flux:button wire:click="resetFilters" variant="ghost" size="sm">Clear filters</flux:button>
        </aside>

        <div>
            <div class="mb-4 flex items-center justify-between">
                <p class="text-sm text-zinc-500 dark:text-zinc-500">
                    {{ $jobPostings->total() }} {{ \Illuminate\Support\Str::plural('opening', $jobPostings->total()) }}
                </p>

                <flux:select wire:model.live="sort" size="sm">
                    <flux:select.option value="newest">Newest</flux:select.option>
                    <flux:select.option value="salary_high">Salary: high to low</flux:select.option>
                    <flux:select.option value="salary_low">Salary: low to high</flux:select.option>
                </flux:select>
            </div>

            @if ($jobPostings->isEmpty())
                <div class="rounded-xl border border-dashed border-zinc-300 px-6 py-16 text-center text-zinc-500 dark:border-zinc-700 dark:text-zinc-500">
                    No openings match these filters yet — try widening your search.
                </div>
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
