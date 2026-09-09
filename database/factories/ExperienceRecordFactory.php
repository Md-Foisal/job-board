<?php

namespace Database\Factories;

use App\Models\CandidateProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ExperienceRecord>
 */
class ExperienceRecordFactory extends Factory
{
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-6 years', '-1 year');
        $isCurrent = fake()->boolean(20);

        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'company_name' => fake()->company(),
            'job_title' => fake()->jobTitle(),
            'description' => fake()->paragraph(),
            'start_date' => $startDate,
            'end_date' => $isCurrent ? null : fake()->dateTimeBetween($startDate, 'now'),
        ];
    }
}
