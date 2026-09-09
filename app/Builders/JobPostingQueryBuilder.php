<?php

namespace App\Builders;

use App\Enums\EmploymentType;
use App\Enums\WorkplaceType;
use Illuminate\Database\Eloquent\Builder;

class JobPostingQueryBuilder extends Builder
{
    /**
     * শুধু ঐ job posting গুলো, যেগুলোর required skill-এর মধ্যে এই skill আছে।
     */
    public function skill(int $skillId): self
    {
        return $this->whereHas('skills', function (Builder $query) use ($skillId) {
            $query->where('skills.id', $skillId);
        });
    }

    /**
     * শুধু ঐ job posting গুলো, যেগুলো এই category-তে আছে।
     */
    public function category(int $categoryId): self
    {
        return $this->whereHas('categories', function (Builder $query) use ($categoryId) {
            $query->where('categories.id', $categoryId);
        });
    }

    /**
     * Candidate-এর দেওয়া salary range-এর সাথে job posting-এর
     * (monthly-normalized) salary range-এর overlap আছে কিনা।
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
     * City name দিয়ে partial-match filter (case-insensitive, DB collation অনুযায়ী)।
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
     * Candidate-এর experience ($years বছর) posting-এর ন্যূনতম চাহিদা পূরণ করে কিনা।
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
