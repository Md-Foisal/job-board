<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            SkillSeeder::class,
            CandidateProfileSeeder::class,
            CompanySeeder::class,
            JobPostingSeeder::class,
            ApplicationSeeder::class,
            DemoAccountsSeeder::class,
            ModerationSeeder::class,
        ]);
    }
}
