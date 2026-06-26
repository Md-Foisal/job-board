<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Application;
use App\Models\JobListing;
use App\Models\User;
use App\Models\Role;

class ApplicationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $candidateRole = Role::where('name', 'candidate')->first();
        $jobs = JobListing::all();
        $candidates = User::factory()
        ->count(100)
        ->create()
        ->each(function ($user) use ($candidateRole) {
            $user->roles()->attach($candidateRole->id);
        });

        foreach ($candidates as $candidate) {
            $randomJobs = $jobs->random(rand(0, 5));

            foreach ($randomJobs as $job) {
                Application::factory()->create([
                    'user_id' => $candidate->id,
                    'job_listing_id' => $job->id,
                ]);
            }
        }
    }
}
