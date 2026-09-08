<?php

namespace Database\Seeders;

use App\Models\Application;
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

        $candidates = User::factory()->count(100)->create();

        foreach ($candidates as $candidate) {
            $randomJobs = $jobs->random(min($jobs->count(), rand(0, 5)));

            foreach ($randomJobs as $job) {
                Application::factory()->create([
                    'user_id' => $candidate->id,
                    'job_posting_id' => $job->id,
                ]);
            }
        }
    }
}
