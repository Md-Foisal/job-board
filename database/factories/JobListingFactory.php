<?php

namespace Database\Factories;

use App\Models\JobListing;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\EmployerProfile;
use Symfony\Component\Intl\Currencies;

/**
 * @extends Factory<JobListing>
 */
class JobListingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $min = $this->faker->numberBetween(20000, 90000);
        $negotiable = $this->faker->boolean(20);

        return [
            'user_id' => User::factory(),
            'employer_profile_id' => EmployerProfile::factory(),
            'title' => $this->faker->jobTitle(),
            'company' => $this->faker->company(),
            'description' => $this->faker->paragraphs(3, true),
            'location' => $this->faker->city(),
            'salary_min' => $negotiable ? null : $min,
            'salary_max' => $negotiable ? null : $min + $this->faker->numberBetween(5000, 40000),
            'salary_currency' => $negotiable ? null : $this->faker->randomElement(Currencies::getCurrencyCodes()),
            'salary_period' => $negotiable ? null : $this->faker->randomElement(['hourly', 'weekly', 'monthly', 'yearly', 'contract']),
            'type' => $this->faker->randomElement(['full-time', 'part-time', 'remote', 'contract', 'internship']),
            'status' => $this->faker->randomElement(['open', 'closed']),
            'expires_at' => $this->faker->dateTimeBetween('+1 day', '+30 days'),
        ];
    }
}
