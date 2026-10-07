<?php

namespace App\Livewire\Concerns;

use App\Builders\JobPostingQueryBuilder;
use App\Enums\EmploymentType;
use App\Enums\WorkplaceType;
use App\Models\Category;
use App\Models\JobPosting;
use App\Models\Skill;
use App\Services\MatchScoreCalculator;
use App\Support\JobSearchCriteria;
use App\Support\PublicCache;
use App\Support\SalaryCurrencies;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

/**
 * Shared filter/sort state + query building for the two full-page,
 * genuinely-reactive job listing components (Job search and Category
 * listing) -- they differ only in whether `category` is user-editable
 * (search) or preset by the route (category page; see categoryIsFixed()).
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

    /**
     * Days since publishing. Not part of the criteria a job alert keeps:
     * an alert only ever sends what is new, so "posted this week" means
     * nothing to it.
     */
    #[Url]
    public ?int $posted = null;

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

        if (! array_key_exists((int) $this->posted, $this->postedOptions())) {
            $this->posted = null;
        }
    }

    /**
     * The search box under the heading asks for a city or "remote", as
     * the one on the home page does. A remote job has no city to match,
     * so the word becomes the workplace filter instead.
     */
    public function updatedLocation(): void
    {
        if (strcasecmp(trim($this->location), WorkplaceType::Remote->value) === 0) {
            $this->location = '';
            $this->workplaceType = WorkplaceType::Remote->value;
        }
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
            ->when($this->postedWithinDays(), fn (JobPostingQueryBuilder $query, int $days) => $query->postedWithin($days))
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

    /**
     * Whether the page itself picks the category (a category page), so
     * the visitor neither chooses nor clears it.
     */
    protected function categoryIsFixed(): bool
    {
        return false;
    }

    /**
     * The choices of the "Date posted" filter, in days.
     *
     * @return array<int, string>
     */
    protected function postedOptions(): array
    {
        return [
            1 => __('Past 24 hours'),
            3 => __('Past 3 days'),
            7 => __('Past week'),
            14 => __('Past 2 weeks'),
        ];
    }

    private function postedWithinDays(): ?int
    {
        return array_key_exists((int) $this->posted, $this->postedOptions()) ? (int) $this->posted : null;
    }

    /**
     * The filters in force, one entry per chip over the results, each
     * named by what clearFilter() takes to remove it. The words typed
     * into the search box are left out: they stay in view in the box.
     * Remote is left out too, since its own toggle shows it.
     *
     * @return list<array{filter: string, label: string}>
     */
    protected function appliedFilters(): array
    {
        $criteria = $this->criteria();
        $chips = [];

        $days = $this->postedWithinDays();

        if ($days !== null) {
            $chips[] = ['filter' => 'posted', 'label' => $this->postedOptions()[$days]];
        }

        if (isset($criteria['workplaceType']) && $criteria['workplaceType'] !== WorkplaceType::Remote->value) {
            $chips[] = ['filter' => 'workplaceType', 'label' => WorkplaceType::from($criteria['workplaceType'])->label()];
        }

        if (isset($criteria['employmentType'])) {
            $chips[] = ['filter' => 'employmentType', 'label' => EmploymentType::from($criteria['employmentType'])->label()];
        }

        if (isset($criteria['category']) && ! $this->categoryIsFixed()) {
            $chips[] = ['filter' => 'category', 'label' => $this->categoryOptions()->firstWhere('id', $criteria['category'])?->name ?? __('A category that was removed')];
        }

        if (isset($criteria['skill'])) {
            $chips[] = ['filter' => 'skill', 'label' => $this->skillOptions()->firstWhere('id', $criteria['skill'])?->name ?? __('A skill that was removed')];
        }

        if (isset($criteria['currency'])) {
            $pay = array_intersect_key($criteria, array_flip(['currency', 'salaryMin', 'salaryMax']));
            $chips[] = ['filter' => 'pay', 'label' => Str::ucfirst(JobSearchCriteria::describe($pay)[0])];
        }

        if (isset($criteria['experience'])) {
            $chips[] = ['filter' => 'experience', 'label' => trans_choice('{1} :count year of experience|[2,*] :count years of experience', $criteria['experience'])];
        }

        return $chips;
    }

    public function clearFilter(string $filter): void
    {
        $properties = match ($filter) {
            'posted', 'workplaceType', 'employmentType', 'skill', 'experience' => [$filter],
            'category' => $this->categoryIsFixed() ? [] : ['category'],
            'pay' => ['currency', 'salaryMin', 'salaryMax'],
            default => [],
        };

        if ($properties === []) {
            return;
        }

        $this->reset($properties);
        $this->forgetUnavailableSort();
        $this->resetPage();
    }

    /**
     * The quick "Remote" chip: the same filter as choosing Remote under
     * workplace, one tap away, because it is the one most often wanted.
     */
    public function toggleRemote(): void
    {
        $this->workplaceType = $this->workplaceType === WorkplaceType::Remote->value ? null : WorkplaceType::Remote->value;
        $this->resetPage();
    }

    /**
     * Clears the filters, not the search: the words and the place in the
     * box above are what the visitor came for, and they stay.
     */
    public function resetFilters(): void
    {
        $this->reset(array_values(array_filter(
            ['posted', 'skill', 'category', 'currency', 'salaryMin', 'salaryMax', 'workplaceType', 'employmentType', 'experience'],
            fn (string $property) => $property !== 'category' || ! $this->categoryIsFixed(),
        )));
        $this->forgetUnavailableSort();
        $this->resetPage();
    }

    /**
     * Re-read on every keystroke otherwise: the filters re-render the
     * whole component.
     */
    protected function skillOptions(): Collection
    {
        return PublicCache::lookupModels('skills', Skill::class, fn () => Skill::orderBy('name')->get());
    }

    protected function categoryOptions(): Collection
    {
        return PublicCache::lookupModels('categories', Category::class, fn () => Category::orderBy('name')->get());
    }

    /**
     * Which of these postings the signed-in person has saved, in one query
     * for the page rather than one per card.
     *
     * @return list<int>
     */
    protected function savedJobIds(Collection $jobPostings): array
    {
        $user = auth()->user();

        if (! $user || ! $user->isCandidate()) {
            return [];
        }

        return $user->savedJobIdsAmong($jobPostings);
    }

    /**
     * Everything the shared listing (partials/job-search) reads, so the
     * two pages hand it the same thing.
     *
     * @return array<string, mixed>
     */
    protected function listing(): array
    {
        $jobPostings = $this->filteredQuery()->paginate(20);
        $applied = $this->appliedFilters();
        $remoteOnly = $this->workplaceType === WorkplaceType::Remote->value;
        $user = auth()->user();

        return [
            'jobPostings' => $jobPostings,
            'matchScores' => $this->matchScores($jobPostings->getCollection()),
            'savedJobIds' => $this->savedJobIds($jobPostings->getCollection()),
            // Guests too: the button signs them in and brings them back.
            // Employers have no list of saved jobs.
            'canSave' => ! $user || $user->isCandidate(),
            'skills' => $this->skillOptions(),
            'categories' => $this->categoryIsFixed() ? collect() : $this->categoryOptions(),
            'workplaceTypes' => WorkplaceType::cases(),
            'employmentTypes' => EmploymentType::cases(),
            'postedOptions' => $this->postedOptions(),
            'payCurrency' => $this->payCurrency(),
            'payCurrencies' => $this->payCurrencyOptions(),
            'canSortByMatch' => $this->canSortByMatch(),
            'appliedFilters' => $applied,
            'remoteOnly' => $remoteOnly,
            'activeFilterCount' => count($applied) + ($remoteOnly ? 1 : 0),
            'searching' => trim($this->q) !== '' || trim($this->location) !== '',
            // Guests too: the link signs them in and brings them back
            // with the search intact. Employers have no alerts to keep.
            'canCreateAlert' => ! $user || $user->isCandidate(),
            'alertUrl' => route('candidate.job-alerts.index', ['create' => 1] + $this->criteria()),
        ];
    }
}
