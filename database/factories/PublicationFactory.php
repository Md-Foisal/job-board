<?php

namespace Database\Factories;

use App\Models\CandidateProfile;
use App\Models\Publication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Publication>
 */
class PublicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'title' => fake()->sentence(5),
            'publisher' => fake()->randomElement(['Laravel News', 'DEV Community', 'Medium']),
            'published_on' => fake()->dateTimeBetween('-3 years', '-1 month'),
            'url' => 'https://'.fake()->domainName().'/'.fake()->slug(3),
            'description' => null,
        ];
    }
}
