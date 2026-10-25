<?php

namespace Database\Factories;

use App\Models\CandidateProfile;
use App\Models\VolunteerExperience;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolunteerExperience>
 */
class VolunteerExperienceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'role' => fake()->randomElement(['Mentor', 'Event organiser', 'Teaching volunteer']),
            'organization' => fake()->company(),
            'start_date' => fake()->dateTimeBetween('-4 years', '-1 year'),
            'end_date' => null,
            'description' => fake()->sentence(12),
        ];
    }
}
