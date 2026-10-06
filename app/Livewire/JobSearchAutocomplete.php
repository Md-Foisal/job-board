<?php

namespace App\Livewire;

use App\Enums\WorkplaceType;
use App\Models\JobPosting;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Live-as-you-type search box used in two places: the homepage hero and
 * the navbar (left out on the pages that carry their own search box --
 * see partials/navbar.blade.php). Suggests
 * a handful of matching job titles while the visitor types; it never
 * replaces the full /jobs search results page (FiltersJobPostings), it
 * just gives a faster way into it, the same way the reusable `keyword()`
 * query-builder method already used there defines "matches this text".
 */
class JobSearchAutocomplete extends Component
{
    /**
     * 'hero' (large, homepage) or 'compact' (navbar, everywhere else) --
     * controls layout/sizing only, not behaviour.
     */
    public string $variant = 'compact';

    public string $q = '';

    /**
     * The hero's second box: a city, or "remote". Search filters on the
     * posting's city, and a remote job has no city to match, so the word
     * "remote" becomes the workplace filter instead.
     */
    public string $where = '';

    public function getResultsProperty(): Collection
    {
        if (mb_strlen(trim($this->q)) < 2) {
            return collect();
        }

        return JobPosting::query()
            ->active()
            ->keyword($this->q)
            ->with('company:id,name')
            ->latest()
            ->latest('id')
            ->limit(6)
            ->get();
    }

    public function goToSearch(): mixed
    {
        $where = trim($this->where);
        $remote = strcasecmp($where, WorkplaceType::Remote->value) === 0;

        return $this->redirect(route('jobs.index', array_filter([
            'q' => trim($this->q),
            'location' => $remote ? '' : $where,
            'workplaceType' => $remote ? WorkplaceType::Remote->value : '',
        ])), navigate: true);
    }

    public function render()
    {
        return view('livewire.job-search-autocomplete');
    }
}
