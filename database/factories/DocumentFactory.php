<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Models\CandidateProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(DocumentType::cases());
        $extension = $type === DocumentType::Cv ? 'pdf' : fake()->randomElement(['pdf', 'jpg', 'png']);

        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'document_type' => $type,
            'file_path' => 'documents/'.fake()->uuid().'.'.$extension,
            'original_filename' => fake()->slug().'.'.$extension,
        ];
    }
}
