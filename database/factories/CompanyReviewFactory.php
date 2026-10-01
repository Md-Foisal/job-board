<?php

namespace Database\Factories;

use App\Enums\ApplicationOutcomeStatus;
use App\Enums\JobAsDescribed;
use App\Enums\ModerationStatus;
use App\Models\Application;
use App\Models\CompanyReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyReview>
 */
class CompanyReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'application_id' => Application::factory()->state([
                'outcome_status' => ApplicationOutcomeStatus::Rejected,
                'decided_at' => now()->subMonth(),
            ]),
            // Always the company the application leads to, never one of
            // its own: a review about a company the writer never applied
            // to is exactly what the table is built to rule out.
            'company_id' => fn (array $attributes) => Application::find($attributes['application_id'])->jobPosting->company_id,
            'overall_rating' => fake()->numberBetween(1, 5),
            'communication_rating' => fake()->numberBetween(1, 5),
            'job_as_described' => fake()->randomElement(JobAsDescribed::cases()),
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(4),
            'moderation_status' => ModerationStatus::Pending,
        ];
    }

    public function published(): static
    {
        return $this->state([
            'moderation_status' => ModerationStatus::Approved,
            'published_at' => now(),
        ]);
    }
}
