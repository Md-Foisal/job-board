<?php

namespace Database\Factories;

use App\Models\Award;
use App\Models\CandidateProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Award>
 */
class AwardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'title' => fake()->randomElement(['First place, batch ranking', 'Hackathon winner', 'Employee of the quarter']),
            'issuer' => fake()->company(),
            'awarded_on' => fake()->dateTimeBetween('-4 years', '-1 month'),
            'description' => fake()->sentence(10),
        ];
    }
}
