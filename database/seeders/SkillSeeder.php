<?php

namespace Database\Seeders;

use App\Models\Skill;
use Database\Seeders\Demo\Catalogue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SkillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (Catalogue::SKILLS as $name) {
            Skill::create([
                'name' => $name,
                'slug' => Str::slug($name),
            ]);
        }
    }
}
