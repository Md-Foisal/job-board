<?php

namespace Database\Factories;

use App\Enums\InvitationStatus;
use App\Enums\MembershipRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Invitation>
 */
class InvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'invited_by_id' => User::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => MembershipRole::Member,
            'token' => Str::random(40),
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addWeek(),
        ];
    }

    /**
     * Still pending, but past its date -- the state the scheduled sweep
     * looks for, and the one the accept page has to refuse.
     */
    public function stale(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }

    public function accepted(): static
    {
        return $this->state(['status' => InvitationStatus::Accepted]);
    }

    public function revoked(): static
    {
        return $this->state(['status' => InvitationStatus::Revoked]);
    }
}
