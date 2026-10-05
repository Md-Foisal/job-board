<?php

namespace Database\Seeders;

use App\Enums\IdentityType;
use App\Models\Company;
use App\Models\Membership;
use App\Models\User;
use Database\Seeders\Demo\Catalogue;
use Database\Seeders\Demo\People;
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
                ->for(self::owner($details), 'user')
                ->create(['job_title' => self::OWNER_TITLES[array_rand(self::OWNER_TITLES)]]);
        }
    }

    /**
     * The person who set the company up: named as people are where it is,
     * with an address made from the name, and the company's zone as their
     * own, since a company starts with its creator's.
     *
     * @param  array<string, mixed>  $details
     */
    private static function owner(array $details): User
    {
        $name = Catalogue::personName($details['locale']);

        return User::factory()->create([
            'name' => $name,
            'email' => People::email($name),
            'timezone' => $details['timezone'],
        ]);
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
