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
     * Postings whose monthly-normalised pay range overlaps the range the
     * candidate asked for.
     */
    public function salaryBetween(?int $min, ?int $max): self
    {
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

    public function sortBy(string $field): self
    {
        return match ($field) {
            'newest' => $this->orderByDesc('created_at'),
            'salary_high' => $this->orderByDesc('salary_max_monthly'),
            'salary_low' => $this->orderBy('salary_min_monthly'),
            default => $this,
        };
    }
}
