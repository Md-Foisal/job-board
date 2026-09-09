<?php

namespace Database\Factories;

use App\Models\CandidateProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\EducationRecord>
 */
class EducationRecordFactory extends Factory
{
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-10 years', '-4 years');
        $isOngoing = fake()->boolean(10);

        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'institution_name' => fake()->company().' University',
            'degree' => fake()->randomElement(['BSc', 'BA', 'MSc', 'MBA', 'Diploma']),
            'field_of_study' => fake()->randomElement(['Computer Science', 'Business Administration', 'Software Engineering', 'Information Technology', 'Marketing']),
            'start_date' => $startDate,
            'end_date' => $isOngoing ? null : fake()->dateTimeBetween($startDate, '-1 year'),
        ];
    }
}
