<?php

namespace Database\Seeders;

use App\Models\Category;
use Database\Seeders\Demo\Catalogue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (Catalogue::CATEGORIES as $name) {
            Category::create([
                'name' => $name,
                'slug' => Str::slug($name),
            ]);
        }
    }
}
