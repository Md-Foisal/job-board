<?php

namespace Database\Seeders;

use App\Models\CandidatePreference;
use App\Models\CandidateProfile;
use App\Models\Document;
use App\Models\EducationRecord;
use App\Models\ExperienceRecord;
use Database\Seeders\Concerns\SeedsCandidateSkills;
use Illuminate\Database\Seeder;

class CandidateProfileSeeder extends Seeder
{
    use SeedsCandidateSkills;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CandidateProfile::factory(10)
            ->has(CandidatePreference::factory(), 'preference')
            ->has(EducationRecord::factory()->count(2))
            ->has(ExperienceRecord::factory()->count(2))
            ->has(Document::factory()->count(2))
            ->create()
            ->each(fn (CandidateProfile $profile) => $this->attachSkills($profile));
    }
}
