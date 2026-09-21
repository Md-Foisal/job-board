<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Models\CandidateProfile;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'document_type' => fake()->randomElement(DocumentType::cases()),
            // Derived from the type actually given, so a test that asks for
            // a CV gets a CV's file name rather than a random picture's.
            'file_path' => fn (array $attributes) => 'documents/'.fake()->uuid().'.'.self::extensionFor($attributes['document_type']),
            'original_filename' => fn (array $attributes) => fake()->slug().'.'.self::extensionFor($attributes['document_type']),
        ];
    }

    /**
     * An extension the upload rules would accept for this kind of document.
     */
    private static function extensionFor(DocumentType|string $type): string
    {
        return match (DocumentType::from($type instanceof DocumentType ? $type->value : $type)) {
            DocumentType::Cv => 'pdf',
            DocumentType::WorkSample => 'png',
            DocumentType::Certificate => 'pdf',
        };
    }
}
