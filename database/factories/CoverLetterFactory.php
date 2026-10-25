<?php

namespace Database\Factories;

use App\Models\CandidateProfile;
use App\Models\CoverLetter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoverLetter>
 */
class CoverLetterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'company_name' => fake()->company(),
            'role_title' => 'Laravel Developer',
            'recipient' => null,
            'body' => fake()->paragraphs(3, true),
        ];
    }
}
