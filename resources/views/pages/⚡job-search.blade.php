<?php

use App\Livewire\Concerns\FiltersJobPostings;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::guest')] #[Title('Job search')] class extends Component {
    use FiltersJobPostings, WithPagination;

    public function with(): array
    {
        return $this->listing();
    }
}; ?>

<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-10">
    <h1 class="mb-5 font-display text-title text-ink">{{ __('Find your next role') }}</h1>

    @include('partials.job-search')
</div>
