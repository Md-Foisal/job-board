<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\JobPosting;
use Illuminate\Database\Seeder;

class JobPostingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Company::with('memberships')->get()->each(function (Company $company) {
            $owner = $company->memberships->first();

            JobPosting::factory()
                ->count(5)
                ->for($company)
                ->create(['posted_by_id' => $owner?->user_id]);
        });
    }
}
