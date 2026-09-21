<?php

namespace App\Livewire;

use App\Models\JobPosting;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Live-as-you-type search box used in two places: the homepage hero and
 * the navbar (shown on every other guest page, since the homepage's own
 * hero search already covers it there -- see guest.blade.php). Suggests
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
        return $this->redirect(route('jobs.index', array_filter(['q' => $this->q])), navigate: true);
    }

    public function render()
    {
        return view('livewire.job-search-autocomplete');
    }
}
