<?php

namespace Database\Factories;

use App\Enums\ApplicationOutcomeStatus;
use App\Enums\ApplicationStage;
use App\Enums\DocumentType;
use App\Models\CandidateProfile;
use App\Models\Document;
use App\Models\JobPosting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_posting_id' => JobPosting::factory(),
            'candidate_profile_id' => CandidateProfile::factory(),
            // The resume snapshot must belong to the same candidate as
            // candidate_profile_id, so it's derived rather than given its
            // own independent factory default.
            'resume_document_id' => fn (array $attributes) => Document::factory()->create([
                'candidate_profile_id' => $attributes['candidate_profile_id'],
                'document_type' => DocumentType::Cv,
            ])->id,
            'cover_letter' => fake()->paragraphs(2, true),
            'outcome_status' => ApplicationOutcomeStatus::Active,
            'stage' => ApplicationStage::New,
        ];
    }
}
