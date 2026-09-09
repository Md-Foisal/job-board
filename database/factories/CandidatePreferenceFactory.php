<?php

namespace Database\Factories;

use App\Enums\EmploymentType;
use App\Enums\WorkplaceType;
use App\Models\CandidateProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Symfony\Component\Intl\Currencies;

/**
 * @extends Factory<\App\Models\CandidatePreference>
 */
class CandidatePreferenceFactory extends Factory
{
    public function definition(): array
    {
        $min = fake()->numberBetween(30, 150) * 1000;
        $max = $min + fake()->numberBetween(10, 80) * 1000;

        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'desired_salary_min' => $min,
            'desired_salary_max' => $max,
            'desired_salary_currency' => fake()->randomElement(Currencies::getCurrencyCodes()),
            'preferred_workplace_type' => fake()->randomElement(WorkplaceType::cases()),
            'preferred_employment_type' => fake()->randomElement(EmploymentType::cases()),
            'is_actively_searching' => fake()->boolean(70),
            'available_from' => fake()->boolean(50) ? fake()->dateTimeBetween('now', '+2 months') : null,
        ];
    }
}
