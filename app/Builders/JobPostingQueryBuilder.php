<?php

namespace App\Builders;

use App\Enums\EmploymentType;
use App\Enums\WorkplaceType;
use Illuminate\Database\Eloquent\Builder;

class JobPostingQueryBuilder extends Builder
{
    /**
     * Only postings that list this skill.
     */
    public function skill(int $skillId): self
    {
        return $this->whereHas('skills', function (Builder $query) use ($skillId) {
            $query->where('skills.id', $skillId);
        });
    }

    /**
     * Only postings in this category.
     */
    public function category(int $categoryId): self
    {
        return $this->whereHas('categories', function (Builder $query) use ($categoryId) {
            $query->where('categories.id', $categoryId);
        });
    }

    /**
     * Postings that state their pay in this currency.
     */
    public function paidIn(string $currency): self
    {
        return $this->where($this->qualifyColumn('salary_currency'), $currency);
    }

    /**
     * Postings in the given currency whose monthly-normalised pay range
     * overlaps the range the candidate asked for. The currency is not
     * optional: there are no exchange rates here, so 50,000 means nothing
     * until it is known to be taka rather than yen.
     */
    public function salaryBetween(string $currency, ?int $min, ?int $max): self
    {
        $this->paidIn($currency);

        if ($min !== null) {
            $this->where('salary_max_monthly', '>=', $min);
        }

        if ($max !== null) {
            $this->where('salary_min_monthly', '<=', $max);
        }

        return $this;
    }

    /**
     * Free-text match on the title, for the candidate-facing search box.
     */
    public function keyword(string $term): self
    {
        return $this->where('title', 'like', "%{$term}%");
    }

    /**
     * Partial match on city name; case sensitivity follows the database collation.
     */
    public function location(string $city): self
    {
        return $this->where('location_city', 'like', "%{$city}%");
    }

    public function workplaceType(WorkplaceType|string $type): self
    {
        return $this->where(
            'workplace_type',
            $type instanceof WorkplaceType ? $type : WorkplaceType::from($type)
        );
    }

    public function employmentType(EmploymentType|string $type): self
    {
        return $this->where(
            'employment_type',
            $type instanceof EmploymentType ? $type : EmploymentType::from($type)
        );
    }

    /**
     * Postings whose minimum experience a candidate with $years years meets.
     */
    public function experience(int $years): self
    {
        return $this->where('min_experience_years', '<=', $years);
    }

    /**
     * Applies a set of search criteria (see JobSearchCriteria) -- the one
     * definition of "matches" the search page and job alerts share.
     */
    public function matching(array $criteria): self
    {
        if (isset($criteria['q'])) {
            $this->keyword($criteria['q']);
        }

        if (isset($criteria['skill'])) {
            $this->skill($criteria['skill']);
        }

        if (isset($criteria['category'])) {
            $this->category($criteria['category']);
        }

        if (isset($criteria['currency'])) {
            isset($criteria['salaryMin']) || isset($criteria['salaryMax'])
                ? $this->salaryBetween($criteria['currency'], $criteria['salaryMin'] ?? null, $criteria['salaryMax'] ?? null)
                : $this->paidIn($criteria['currency']);
        }

        if (isset($criteria['location'])) {
            $this->location($criteria['location']);
        }

        if (isset($criteria['workplaceType'])) {
            $this->workplaceType($criteria['workplaceType']);
        }

        if (isset($criteria['employmentType'])) {
            $this->employmentType($criteria['employmentType']);
        }

        if (isset($criteria['experience'])) {
            $this->experience($criteria['experience']);
        }

        return $this;
    }

    /**
     * Every sort ends on the id, newest first: many postings share a date
     * or a salary, and tied rows may come back in any order -- a paginated
     * list would then repeat some postings and skip others between pages.
     *
     * Pay is sorted only within one currency. Postings in it come first,
     * by pay, those with no figure after them; everything else follows,
     * newest first, rather than being ranked by numbers in other units.
     * Without a currency a pay sort is simply newest first.
     *
     * Each step is a CASE that sorts a known value, never a bare column
     * that may be null: MySQL and SQLite put nulls first in an ascending
     * sort, PostgreSQL puts them first in a descending one, so "high to
     * low" would open with negotiable postings on one of them.
     */
    public function sortBy(string $field, ?string $currency = null): self
    {
        if (in_array($field, ['salary_high', 'salary_low'], true) && $currency !== null) {
            $high = $field === 'salary_high';
            $inCurrency = $this->qualifyColumn('salary_currency').' = ?';
            $pay = $high
                ? 'coalesce('.$this->qualifyColumn('salary_max_monthly').', '.$this->qualifyColumn('salary_min_monthly').')'
                : 'coalesce('.$this->qualifyColumn('salary_min_monthly').', '.$this->qualifyColumn('salary_max_monthly').')';

            $this->orderByRaw("case when {$inCurrency} then 0 else 1 end", [$currency])
                ->orderByRaw("case when {$inCurrency} and {$pay} is not null then 0 else 1 end", [$currency])
                ->orderByRaw("case when {$inCurrency} then {$pay} else 0 end ".($high ? 'desc' : 'asc'), [$currency]);
        }

        if ($field === 'newest' || in_array($field, ['salary_high', 'salary_low'], true)) {
            $this->orderByDesc($this->qualifyColumn('created_at'));
        }

        return $this->orderByDesc($this->qualifyColumn('id'));
    }
}
