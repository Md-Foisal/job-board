<?php

namespace Database\Seeders;

use App\Enums\IdentityType;
use App\Models\Company;
use App\Models\Membership;
use App\Models\User;
use Database\Seeders\Demo\Catalogue;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    private const OWNER_TITLES = ['Founder', 'Chief Executive', 'Head of People', 'Talent Lead', 'Operations Director'];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (Catalogue::companies() as $slug => $details) {
            $company = self::create($slug, $details);

            Membership::factory()
                ->owner()
                ->for($company)
                ->for(User::factory()->state(['name' => Catalogue::personName($details['locale'])]), 'user')
                ->create(['job_title' => self::OWNER_TITLES[array_rand(self::OWNER_TITLES)]]);
        }
    }

    /**
     * A company from the catalogue, joined a while ago as a real one
     * would have been.
     *
     * @param  array<string, mixed>  $details
     * @param  array<string, mixed>  $overrides
     */
    public static function create(string $slug, array $details, array $overrides = []): Company
    {
        $joined = now()->subDays(random_int(120, 900));

        $company = Company::factory()->create([
            'name' => $details['name'],
            'slug' => $slug,
            'identity_type' => IdentityType::from($details['identity']),
            'description' => $details['about'],
            'website_url' => 'https://'.str_replace('-', '', $slug).'.example',
            'size' => $details['size'],
            'industry' => $details['industry'],
            'timezone' => $details['timezone'],
            ...$overrides,
        ]);

        $company->forceFill(['created_at' => $joined, 'updated_at' => $joined])->saveQuietly();

        return $company;
    }
}
