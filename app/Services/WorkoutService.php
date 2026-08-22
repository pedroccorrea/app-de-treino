<?php

namespace App\Services;

use App\Enums\DayOfWeek;
use App\Enums\MuscleGroup;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class WorkoutService
{
    /**
     * Fetches the user's workouts ordered by their scheduled weekday
     * (Monday → Sunday). Workouts without a scheduled day are listed last.
     *
     * @return Collection<int, Workout>
     */
    public function getUserWorkouts(User $user): Collection
    {
        return $user->workouts()
            ->with('exercises')
            ->latest()
            ->get()
            ->sortBy(function (Workout $workout) {
                $days = $workout->days_of_week;

                if (! $days || $days->isEmpty()) {
                    return PHP_INT_MAX;
                }

                return $days->map(fn (DayOfWeek $day) => $day->value)->min();
            })
            ->values();
    }

    public function todayDayOfWeek(): DayOfWeek
    {
        return DayOfWeek::from(now()->dayOfWeekIso);
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
                'days_of_week' => $data['days_of_week'] ?? null,
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

    /**
     * Updates a workout's own data and syncs its exercises against the
     * `workout_exercises` pivot: exercises no longer submitted are removed,
     * the rest are upserted with their (possibly new) order/sets/reps.
     */
    public function updateWorkout(Workout $workout, array $data): Workout
    {
        return DB::transaction(function () use ($workout, $data) {
            $workout->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'days_of_week' => $data['days_of_week'] ?? null,
            ]);

            $exercises = collect($data['exercises'] ?? []);

            $workout->workoutExercises()
                ->whereNotIn('exercise_id', $exercises->pluck('id'))
                ->delete();

            foreach ($exercises as $index => $exercise) {
                $workout->workoutExercises()->updateOrCreate(
                    ['exercise_id' => $exercise['id']],
                    [
                        'order' => $exercise['order'] ?? $index,
                        'target_sets' => $exercise['target_sets'] ?? null,
                        'target_reps' => $exercise['target_reps'] ?? null,
                    ]
                );
            }

            return $workout->load('exercises');
        });
    }

    /**
     * Persists the new sort order of exercises in the `workout_exercises` pivot.
     *
     * @param  array<int>  $exerciseIds  Ordered array of exercise IDs.
     */
    public function reorderExercises(Workout $workout, array $exerciseIds): void
    {
        DB::transaction(function () use ($workout, $exerciseIds) {
            foreach ($exerciseIds as $position => $exerciseId) {
                $workout->workoutExercises()
                    ->where('exercise_id', $exerciseId)
                    ->update(['order' => $position]);
            }
        });
    }
}
