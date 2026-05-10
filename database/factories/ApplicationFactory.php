<?php

namespace Database\Factories;

use App\Models\Application;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\JobListing;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
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
            'job_listing_id' => JobListing::factory(),
            'cover_letter' => $this->faker->paragraphs(2, true),
            'resume' => 'resumes/dummy_resume.pdf', // You can use a dummy file path for testing
            'status' => $this->faker->randomElement(['pending', 'accepted', 'rejected']),
        ];
    }
}
