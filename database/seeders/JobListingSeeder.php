<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Role;
use App\Models\JobListing;
use App\Models\EmployerProfile;

class JobListingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employerRole = Role::where('name', 'employer')->first();

        EmployerProfile::all()->each(function (EmployerProfile $employerProfile) use ($employerRole) {
            $employerProfile->user->roles()->syncWithoutDetaching($employerRole->id);

            JobListing::factory()
                ->count(5)
                ->for($employerProfile->user)
                ->for($employerProfile)
                ->create();
        });
    }
}
