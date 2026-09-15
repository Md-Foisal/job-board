<?php

namespace App\Livewire\Concerns;

use App\Builders\JobPostingQueryBuilder;
use App\Models\JobPosting;
use App\Services\MatchScoreCalculator;
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

    protected function filteredQuery(): JobPostingQueryBuilder
    {
        $query = JobPosting::query()
            ->active()
            ->with(['company:id,name,slug,logo_path,verified_at', 'skills:id,name']);

        if ($this->q !== '') {
            $query->keyword($this->q);
        }

        if ($this->skill) {
            $query->skill($this->skill);
        }

        if ($this->category) {
            $query->category($this->category);
        }

        if ($this->salaryMin || $this->salaryMax) {
            $query->salaryBetween($this->salaryMin, $this->salaryMax);
        }

        if ($this->location !== '') {
            $query->location($this->location);
        }

        if ($this->workplaceType) {
            $query->workplaceType($this->workplaceType);
        }

        if ($this->employmentType) {
            $query->employmentType($this->employmentType);
        }

        if ($this->experience) {
            $query->experience($this->experience);
        }

        return $query->sortBy($this->sort);
    }

    /**
     * The logged-in candidate's own skill IDs, loaded once per request --
     * never per job-card -- so match score stays a single extra query
     * regardless of how many postings are on the page.
     */
    protected function candidateSkillIds(): Collection
    {
        $user = auth()->user();

        if (!$user || !$user->isCandidate()) {
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
        $this->reset(['q', 'skill', 'category', 'salaryMin', 'salaryMax', 'location', 'workplaceType', 'employmentType', 'experience']);
        $this->resetPage();
    }
}
