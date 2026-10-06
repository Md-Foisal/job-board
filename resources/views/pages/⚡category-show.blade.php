<?php

use App\Livewire\Concerns\FiltersJobPostings;
use App\Models\Category;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::guest')] #[Title('Category jobs')] class extends Component {
    use FiltersJobPostings, WithPagination;

    public Category $categoryModel;

    public function mount(Category $categoryModel): void
    {
        // The route resolves removed categories too, only so that a merged
        // one can forward permanently to where its postings went. Anything
        // else that has been removed is simply gone.
        if ($categoryModel->trashed()) {
            $target = $categoryModel->merged_into_id ? Category::find($categoryModel->merged_into_id) : null;

            abort_if($target === null, 404);

            // Built directly: inside a Livewire component redirect() returns
            // Livewire's own redirector, which cannot carry a 301.
            throw new HttpResponseException(new RedirectResponse(route('categories.show', $target), 301));
        }

        $this->categoryModel = $categoryModel;
        $this->category = $categoryModel->id;
    }

    public function with(): array
    {
        $jobPostings = $this->filteredQuery()->paginate(20);

        return [
            'jobPostings' => $jobPostings,
            'matchScores' => $this->matchScores($jobPostings->getCollection()),
            'payCurrency' => $this->payCurrency(),
            'payCurrencies' => $this->payCurrencyOptions(),
            'canSortByMatch' => $this->canSortByMatch(),
        ];
    }
}; ?>

<div class="mx-auto max-w-6xl px-6 py-10">
    <x-breadcrumb :items="[['label' => $categoryModel->name]]" />

    <h1 class="mt-2 font-display text-2xl font-bold text-ink">{{ $categoryModel->name }} jobs</h1>

    <div class="mt-6 flex flex-wrap items-center gap-3">
        <flux:input wire:model.live.debounce.400ms="location" placeholder="Filter by city" class="max-w-xs" />

        <flux:select wire:model.live="currency" size="sm" class="max-w-xs" :aria-label="__('Pay currency')">
            <flux:select.option value="">{{ __('Pay in any currency') }}</flux:select.option>
            @foreach ($payCurrencies as $code => $label)
                <flux:select.option value="{{ $code }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="sort" size="sm" class="max-w-xs" :aria-label="__('Sort')">
            <flux:select.option value="newest">{{ __('Newest') }}</flux:select.option>
            @if ($canSortByMatch)
                <flux:select.option value="match">{{ __('Best match') }}</flux:select.option>
            @endif
            @if ($payCurrency)
                <flux:select.option value="salary_high">{{ __('Pay in :currency: high to low', ['currency' => $payCurrency]) }}</flux:select.option>
                <flux:select.option value="salary_low">{{ __('Pay in :currency: low to high', ['currency' => $payCurrency]) }}</flux:select.option>
            @endif
        </flux:select>

        <a href="{{ route('jobs.index') }}" class="text-sm text-sunset-small hover:underline" wire:navigate>
            Search all categories &rarr;
        </a>
    </div>

    <p class="mt-4 text-sm text-ink-muted">
        {{ $jobPostings->total() }} {{ \Illuminate\Support\Str::plural('opening', $jobPostings->total()) }} in {{ $categoryModel->name }}
    </p>

    @if ($jobPostings->isEmpty())
        <x-empty-state icon="briefcase" class="mt-6" :heading="__('No open :category positions right now', ['category' => $categoryModel->name])" :action-href="route('jobs.index')" :action-label="__('Browse all open roles')">
            {{ __('Check back soon, or look through the roles open in every field.') }}
        </x-empty-state>
    @else
        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($jobPostings as $jobPosting)
                <x-job-card :job-posting="$jobPosting" :match-score="$matchScores[$jobPosting->id] ?? null" wire:key="job-{{ $jobPosting->id }}" />
            @endforeach
        </div>

        <div class="mt-8">
            {{ $jobPostings->links() }}
        </div>
    @endif
</div>
