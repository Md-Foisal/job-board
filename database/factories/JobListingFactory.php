<?php

namespace Database\Factories;

use App\Models\JobListing;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;

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
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->jobTitle(),
            'company' => $this->faker->company(),
            'description' => $this->faker->paragraphs(3, true),
            'location' => $this->faker->city(),
            'salary' => $this->faker->randomElement(['$50,000 - $70,000', '$70,000 - $90,000', '$90,000 - $120,000', null]),
            'type' => $this->faker->randomElement(['full-time', 'part-time', 'remote', 'contract', 'internship']),
            'status' => $this->faker->randomElement(['open', 'closed']),
        ];
    }
}
