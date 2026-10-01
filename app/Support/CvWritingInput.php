<?php

namespace App\Support;

use App\Models\CandidateProfile;
use App\Models\EducationRecord;
use App\Models\ExperienceRecord;

/**
 * What is sent to the AI to suggest CV wording: the professional profile
 * only. Polishing text needs no idea who wrote it, so the name, email,
 * phone, location, links, photo and preferences never go out.
 *
 * Each role carries its id, so a suggestion can only ever land on the
 * role it was written for.
 */
final class CvWritingInput
{
    private const SUMMARY_MAX = 5000;

    private const DESCRIPTION_MAX = 2000;

    /**
     * @return array{headline: ?string, summary: ?string, experience: array<int, array<string, mixed>>, education: array<int, array<string, mixed>>, skills: array<int, string>}
     */
    public static function for(CandidateProfile $profile): array
    {
        return [
            'headline' => filled($profile->headline) ? $profile->headline : null,
            'summary' => filled($profile->bio) ? mb_substr(trim($profile->bio), 0, self::SUMMARY_MAX) : null,
            'experience' => $profile->experienceRecords()->orderByDesc('start_date')->get()
                ->map(fn (ExperienceRecord $record) => [
                    'id' => $record->id,
                    'job_title' => $record->job_title,
                    'company' => $record->company_name,
                    'from' => $record->start_date->format('Y-m'),
                    'to' => $record->end_date?->format('Y-m') ?? 'present',
                    'description' => RichText::plain($record->description, self::DESCRIPTION_MAX),
                ])->all(),
            'education' => $profile->educationRecords()->orderByDesc('start_date')->get()
                ->map(fn (EducationRecord $record) => [
                    'institution' => $record->institution_name,
                    'degree' => $record->degree,
                    'field_of_study' => $record->field_of_study,
                    'from' => $record->start_date->format('Y-m'),
                    'to' => $record->end_date?->format('Y-m') ?? 'present',
                ])->all(),
            'skills' => $profile->skills()->orderBy('name')->pluck('name')->all(),
        ];
    }

    /**
     * The whole input as one JSON object, which is what the model receives,
     * with angle brackets escaped so nothing in it can read as markup.
     *
     * @param  array<string, mixed>  $input
     */
    public static function material(array $input): string
    {
        return json_encode($input, JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
