<?php

namespace Database\Factories;

use App\Models\CandidateProfile;
use App\Models\CvVersion;
use App\Support\CvDesign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CvVersion>
 */
class CvVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'name' => 'My CV',
            'design' => (new CvDesign)->toArray(),
            'extras' => null,
            'hidden_entries' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'public_slug' => CvVersion::newSlug(),
            'published_at' => now(),
        ]);
    }
}
