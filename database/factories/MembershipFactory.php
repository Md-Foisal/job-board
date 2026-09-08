<?php

namespace Database\Factories;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Company;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_id' => Company::factory(),
            'role' => MembershipRole::Member,
            'job_title' => $this->faker->jobTitle(),
            'status' => MembershipStatus::Active,
        ];
    }

    public function owner(): static
    {
        return $this->state(['role' => MembershipRole::Owner]);
    }

    public function manager(): static
    {
        return $this->state(['role' => MembershipRole::Manager]);
    }
}
