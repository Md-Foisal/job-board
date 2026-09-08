<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Membership;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Company::factory(10)->create()->each(function (Company $company) {
            Membership::factory()
                ->owner()
                ->for($company)
                ->create();
        });
    }
}
