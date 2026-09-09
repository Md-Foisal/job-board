<?php

namespace Database\Seeders;

use App\Enums\DocumentType;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Document;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Database\Seeder;

class ApplicationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jobs = JobPosting::all();

        User::factory()
            ->count(100)
            ->has(CandidateProfile::factory(), 'candidateProfile')
            ->create()
            ->each(function (User $candidate) use ($jobs) {
                $candidateProfile = $candidate->candidateProfile;

                $resume = Document::factory()->create([
                    'candidate_profile_id' => $candidateProfile->id,
                    'document_type' => DocumentType::Cv,
                ]);

                $randomJobs = $jobs->random(min($jobs->count(), rand(0, 5)));

                foreach ($randomJobs as $job) {
                    Application::factory()->create([
                        'job_posting_id' => $job->id,
                        'candidate_profile_id' => $candidateProfile->id,
                        'resume_document_id' => $resume->id,
                    ]);
                }
            });
    }
}
