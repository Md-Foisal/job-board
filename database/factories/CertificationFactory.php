<?php

namespace Database\Factories;

use App\Models\CandidateProfile;
use App\Models\Certification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certification>
 */
class CertificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'name' => fake()->randomElement(['AWS Certified Cloud Practitioner', 'Laravel Certification', 'Google UX Design Certificate']),
            'issuer' => fake()->company(),
            'issued_on' => fake()->dateTimeBetween('-3 years', '-1 month'),
            'expires_on' => null,
            'credential_id' => strtoupper(fake()->bothify('??-#####')),
            'credential_url' => fake()->url(),
        ];
    }
}
