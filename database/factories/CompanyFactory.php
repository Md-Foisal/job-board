<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Enums\IdentityType;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1000, 99999),
            'identity_type' => $this->faker->randomElement(IdentityType::cases()),
            'description' => $this->faker->paragraph(),
            'website_url' => $this->faker->url(),
            'logo_path' => null,
            'size' => $this->faker->randomElement(['1-10', '11-50', '51-200', '201-500', '500+']),
            'industry' => $this->faker->randomElement(['Technology', 'Finance', 'Healthcare', 'Education', 'Retail']),
            'account_status' => AccountStatus::Active,
        ];
    }
}
