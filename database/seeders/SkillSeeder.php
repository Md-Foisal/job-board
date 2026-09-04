<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

use App\Models\Skill;

class SkillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $skills = [
            'Laravel', 
            'PHP', 
            'JavaScript', 
            'React', 
            'Vue.js', 
            'Python', 
            'MySQL', 
            'HTML', 
            'CSS', 
            'Git', 
            'Node.js', 
            'TypeScript'
        ];

        foreach ($skills as $name) {
            Skill::create([
                'name' => $name,
                'slug' => Str::slug($name),
            ]);
        }
    }
}
