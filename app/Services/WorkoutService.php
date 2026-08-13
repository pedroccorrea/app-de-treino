<?php

namespace App\Services;

use App\Enums\MuscleGroup;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class WorkoutService
{
    /**
     * @return Collection<int, Workout>
     */
    public function getUserWorkouts(User $user): Collection
    {
        return $user->workouts()
            ->with('exercises')
            ->latest()
            ->get();
    }

    /**
     * @return array<int, array{muscle: string, muscle_key: string, exercises: array<int, array{id: int, name: string, primary_muscle: string}>}>
     */
    public function getAvailableExercises(): array
    {
        return Exercise::query()
            ->global()
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Exercise $exercise) => $exercise->primary_muscle_group->value)
            ->map(function ($exercises, string $muscleKey) {
                return [
                    'muscle' => MuscleGroup::from($muscleKey)->label(),
                    'muscle_key' => $muscleKey,
                    'exercises' => $exercises->map(fn (Exercise $exercise) => [
                        'id' => $exercise->id,
                        'name' => $exercise->name,
                        'primary_muscle' => $exercise->primary_muscle_group->label(),
                    ])->values()->all(),
                ];
            })
            ->sortBy('muscle')
            ->values()
            ->all();
    }

    public function createWorkout(User $user, array $data): Workout
    {
        return DB::transaction(function () use ($user, $data) {
            $workout = $user->workouts()->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            foreach ($data['exercises'] ?? [] as $index => $exercise) {
                $workout->workoutExercises()->create([
                    'exercise_id' => $exercise['id'],
                    'order' => $exercise['order'] ?? $index,
                    'target_sets' => $exercise['target_sets'] ?? null,
                    'target_reps' => $exercise['target_reps'] ?? null,
                ]);
            }

            return $workout->load('exercises');
        });
    }
}
