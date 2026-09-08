<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

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
            'job_posting_id' => JobPosting::factory(),
            'cover_letter' => $this->faker->paragraphs(2, true),
            'resume' => 'resumes/dummy_resume.pdf',
            'status' => $this->faker->randomElement(ApplicationStatus::cases()),
        ];
    }
}
