<?php

namespace Database\Factories;

use App\Enums\LanguageLevel;
use App\Models\CandidateLanguage;
use App\Models\CandidateProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CandidateLanguage>
 */
class CandidateLanguageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'name' => fake()->unique()->randomElement(['English', 'Bengali', 'Hindi', 'Arabic', 'German', 'French', 'Japanese']),
            'level' => fake()->randomElement(LanguageLevel::cases()),
        ];
    }
}
