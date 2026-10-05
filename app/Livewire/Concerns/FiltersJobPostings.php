<?php

namespace App\Livewire\Concerns;

use App\Builders\JobPostingQueryBuilder;
use App\Models\JobPosting;
use App\Services\MatchScoreCalculator;
use App\Support\JobSearchCriteria;
use App\Support\PublicCache;
use App\Support\SalaryCurrencies;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Shared filter/sort state + query building for the two full-page,
 * genuinely-reactive job listing components (Job search and Category
 * listing) -- they differ only in whether `category` is user-editable
 * (search) or preset by the route (category page).
 */
trait FiltersJobPostings
{
    #[Url]
    public string $q = '';

    #[Url]
    public ?int $skill = null;

    #[Url]
    public ?int $category = null;

    #[Url]
    public ?string $currency = null;

    #[Url]
    public ?int $salaryMin = null;

    #[Url]
    public ?int $salaryMax = null;

    #[Url]
    public string $location = '';

    #[Url]
    public ?string $workplaceType = null;

    #[Url]
    public ?string $employmentType = null;

    #[Url]
    public ?int $experience = null;

    #[Url]
    public string $sort = 'newest';

    public function updating($property): void
    {
        if ($property !== 'sort') {
            $this->resetPage();
        }
    }

    /**
     * The pay sorts exist only once a currency is chosen, and "best match"
     * only for a candidate with skills, so the sort never points at an
     * option the page is not offering -- after the currency is cleared, or
     * from a link someone else shared.
     */
    public function mountFiltersJobPostings(): void
    {
        $this->forgetUnavailableSort();
    }

    public function updatedCurrency(): void
    {
        $this->forgetUnavailableSort();
    }

    protected function filteredQuery(): JobPostingQueryBuilder
    {
        $criteria = $this->criteria();

        return JobPosting::query()
            ->active()
            ->with(['company:id,name,slug,logo_path,verified_at', 'skills:id,name'])
            ->matching($criteria)
            ->sortBy($this->sort, $criteria['currency'] ?? null, $this->sort === 'match' ? $this->candidateSkillIds()->all() : []);
    }

    /**
     * Whether "best match" can be offered: only a candidate who has listed
     * skills has anything to be matched on.
     */
    protected function canSortByMatch(): bool
    {
        return $this->candidateSkillIds()->isNotEmpty();
    }

    /**
     * The currency the pay filter and the pay sort work in, once it is a
     * real one.
     */
    protected function payCurrency(): ?string
    {
        return $this->criteria()['currency'] ?? null;
    }

    /**
     * Only the currencies open postings are actually paid in, rather than
     * all 150: the list stays short, and every choice in it has postings
     * behind it. A currency already chosen stays in it even if its last
     * posting has closed.
     *
     * @return array<string, string>
     */
    protected function payCurrencyOptions(): array
    {
        $codes = PublicCache::remember('pay-currencies', fn () => JobPosting::query()
            ->active()
            ->whereNotNull('salary_currency')
            ->distinct()
            ->orderBy('salary_currency')
            ->pluck('salary_currency')
            ->all());

        $chosen = $this->payCurrency();

        if ($chosen !== null && ! in_array($chosen, $codes, true)) {
            $codes[] = $chosen;
        }

        return array_intersect_key(SalaryCurrencies::options(), array_flip($codes));
    }

    private function forgetUnavailableSort(): void
    {
        $available = match ($this->sort) {
            'salary_high', 'salary_low' => $this->payCurrency() !== null,
            'match' => $this->canSortByMatch(),
            default => true,
        };

        if (! $available) {
            $this->sort = 'newest';
        }
    }

    /**
     * The filters as they stand, in the shape a job alert stores them.
     *
     * @return array<string, int|string>
     */
    protected function criteria(): array
    {
        return JobSearchCriteria::from([
            'q' => $this->q,
            'skill' => $this->skill,
            'category' => $this->category,
            'location' => $this->location,
            'workplaceType' => $this->workplaceType,
            'employmentType' => $this->employmentType,
            'currency' => $this->currency,
            'salaryMin' => $this->salaryMin,
            'salaryMax' => $this->salaryMax,
            'experience' => $this->experience,
        ]);
    }

    /**
     * The logged-in candidate's own skill IDs, loaded once per request --
     * never per job-card -- so match score stays a single extra query
     * regardless of how many postings are on the page.
     */
    protected function candidateSkillIds(): Collection
    {
        $user = auth()->user();

        if (! $user || ! $user->isCandidate()) {
            return collect();
        }

        return $user->candidateProfile->skills->pluck('id');
    }

    protected function matchScores(Collection $jobPostings): array
    {
        $candidateSkillIds = $this->candidateSkillIds();

        if ($candidateSkillIds->isEmpty()) {
            return [];
        }

        $calculator = app(MatchScoreCalculator::class);

        return $jobPostings->mapWithKeys(
            fn (JobPosting $jobPosting) => [$jobPosting->id => $calculator->calculate($jobPosting, $candidateSkillIds)]
        )->all();
    }

    public function resetFilters(): void
    {
        $this->reset(['q', 'skill', 'category', 'currency', 'salaryMin', 'salaryMax', 'location', 'workplaceType', 'employmentType', 'experience']);
        $this->forgetUnavailableSort();
        $this->resetPage();
    }
}
