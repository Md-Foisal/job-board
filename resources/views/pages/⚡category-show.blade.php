<?php

use App\Livewire\Concerns\FiltersJobPostings;
use App\Models\Category;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::guest')] class extends Component {
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

    protected function categoryIsFixed(): bool
    {
        return true;
    }

    public function with(): array
    {
        return $this->listing();
    }

    public function render()
    {
        return $this->view()->title(__(':category jobs', ['category' => $this->categoryModel->name]));
    }
}; ?>

<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-10">
    <x-breadcrumb :items="[['label' => $categoryModel->name]]" />

    <div class="mt-3 mb-5 flex items-center gap-3">
        <x-icon-tile :icon="\App\Support\CategoryIcon::for($categoryModel)" />
        <div class="min-w-0">
            <h1 class="font-display text-title text-ink">{{ __(':category jobs', ['category' => $categoryModel->name]) }}</h1>
            <a href="{{ route('jobs.index') }}" class="text-sm font-medium" wire:navigate>
                <span class="text-sunset-small hover:underline">{{ __('Search every category') }} &rarr;</span>
            </a>
        </div>
    </div>

    @include('partials.job-search')
</div>
