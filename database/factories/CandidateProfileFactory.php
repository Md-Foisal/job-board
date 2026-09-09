<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\CandidateProfile>
 */
class CandidateProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'headline' => fake()->jobTitle(),
            'bio' => fake()->paragraph(),
            'portfolio_url' => fake()->boolean(50) ? fake()->url() : null,
            'github_url' => fake()->boolean(50) ? 'https://github.com/'.fake()->userName() : null,
            'linkedin_url' => fake()->boolean(50) ? 'https://linkedin.com/in/'.fake()->userName() : null,
        ];
    }
}
