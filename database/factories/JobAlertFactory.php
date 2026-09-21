<?php

namespace Database\Factories;

use App\Enums\AlertFrequency;
use App\Models\JobAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobAlert>
 */
class JobAlertFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->jobTitle(),
            'criteria' => ['q' => 'Developer'],
            'frequency' => AlertFrequency::Daily,
            'is_active' => true,
        ];
    }

    public function weekly(): static
    {
        return $this->state(['frequency' => AlertFrequency::Weekly]);
    }

    public function paused(): static
    {
        return $this->state(['is_active' => false]);
    }
}
