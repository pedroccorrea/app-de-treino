<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkoutProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkoutProgram>
 */
class WorkoutProgramFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'is_active' => false,
            'archived_at' => null,
        ];
    }
}
