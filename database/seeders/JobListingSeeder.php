<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Role;
use App\Models\JobListing;
use App\Models\User;

class JobListingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employerRole = Role::where('name', 'employer')->first();
        User::factory()
        ->count(5)
        ->has(JobListing::factory()
        ->count(5))
        ->create()
        ->each(function ($user) use ($employerRole) {
            $user->roles()->attach($employerRole->id);
        });
    }
}
