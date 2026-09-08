<?php

namespace Database\Factories;

use App\Enums\AvailabilityStatus;
use App\Enums\EmploymentType;
use App\Enums\ModerationStatus;
use App\Enums\SalaryPeriod;
use App\Enums\WorkplaceType;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Symfony\Component\Intl\Currencies;

/**
 * @extends Factory<JobPosting>
 */
class JobPostingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->jobTitle();
        $negotiable = $this->faker->boolean(20);
        $min = $this->faker->numberBetween(20000, 90000);

        return [
            'company_id' => Company::factory(),
            'posted_by_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.$this->faker->unique()->numberBetween(1000, 999999),
            'description' => $this->faker->paragraphs(3, true),
            'employment_type' => $this->faker->randomElement(EmploymentType::cases()),
            'workplace_type' => $this->faker->randomElement(WorkplaceType::cases()),
            'location_city' => $this->faker->city(),
            'location_country' => $this->faker->country(),
            'min_experience_years' => $this->faker->numberBetween(0, 10),
            'salary_min' => $negotiable ? null : $min,
            'salary_max' => $negotiable ? null : $min + $this->faker->numberBetween(5000, 40000),
            'salary_currency' => $negotiable ? null : $this->faker->randomElement(Currencies::getCurrencyCodes()),
            'salary_period' => $negotiable ? null : $this->faker->randomElement(SalaryPeriod::cases()),
            'salary_negotiable' => $negotiable,
            'availability_status' => AvailabilityStatus::Active,
            'moderation_status' => ModerationStatus::Approved,
            'expires_at' => $this->faker->dateTimeBetween('+1 day', '+30 days'),
        ];
    }

    public function draft(): static
    {
        return $this->state(['availability_status' => AvailabilityStatus::Draft]);
    }

    public function pendingModeration(): static
    {
        return $this->state(['moderation_status' => ModerationStatus::Pending]);
    }
}
