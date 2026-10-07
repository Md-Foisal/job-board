<?php

namespace Database\Factories;

use App\Models\CandidateProfile;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        $slug = fake()->slug(2);

        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(12),
            'url' => 'https://'.$slug.'.example',
            'source_url' => 'https://github.com/'.fake()->userName().'/'.$slug,
            'start_date' => fake()->dateTimeBetween('-3 years', '-6 months'),
            'end_date' => null,
        ];
    }
}
