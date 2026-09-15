<?php

namespace Database\Seeders;

use App\Enums\SkillImportance;
use App\Models\Category;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Skill;
use Illuminate\Database\Seeder;

class JobPostingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categoryIds = Category::pluck('id');
        $skillIds = Skill::pluck('id');

        Company::with('memberships')->get()->each(function (Company $company) use ($categoryIds, $skillIds) {
            $owner = $company->memberships->first();

            JobPosting::factory()
                ->count(5)
                ->for($company)
                ->create(['posted_by_id' => $owner?->user_id])
                ->each(function (JobPosting $jobPosting) use ($categoryIds, $skillIds) {
                    $jobPosting->categories()->attach(
                        $categoryIds->random(min(2, $categoryIds->count()))
                    );

                    $skillCount = min(fake()->numberBetween(3, 6), $skillIds->count());

                    $jobPosting->skills()->attach(
                        $skillIds->random($skillCount)->mapWithKeys(fn ($skillId) => [
                            $skillId => ['importance' => fake()->randomElement(SkillImportance::cases())->value],
                        ])
                    );
                });
        });
    }
}
