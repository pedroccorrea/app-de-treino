<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\WorkoutSession>
 */
class WorkoutSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'workout_id' => Workout::factory(),
            'started_at' => now(),
            'completed_at' => null,
            'notes' => null,
        ];
    }
}
