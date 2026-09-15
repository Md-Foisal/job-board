<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ApplicationNote>
 */
class ApplicationNoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'author_id' => User::factory(),
            'note' => fake()->paragraph(),
        ];
    }
}
