<?php

use App\Models\Exercise;
use App\Models\User;
use Database\Seeders\ExerciseSeeder;

test('activeWorkoutSession prop is shared when the user has an open session', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();

    $workout = $user->workouts()->create(['name' => 'Treino Peito']);
    $workout->workoutExercises()->create([
        'exercise_id' => $exercise->id,
        'order' => 0,
        'target_sets' => 3,
        'target_reps' => '10',
    ]);

    $session = $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->where('activeWorkoutSession', [
                'id' => $session->id,
                'workout_name' => 'Treino Peito',
                'started_at' => $session->started_at->toJSON(),
            ])
        );
});

test('activeWorkoutSession prop is absent when all sessions are finished', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();

    $workout = $user->workouts()->create(['name' => 'Treino Peito']);
    $workout->workoutExercises()->create([
        'exercise_id' => $exercise->id,
        'order' => 0,
        'target_sets' => 3,
        'target_reps' => '10',
    ]);

    $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now()->subHour(),
        'completed_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->where('activeWorkoutSession', null)
        );
});

test('activeWorkoutSession prop is absent for guests', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->where('activeWorkoutSession', null)
        );
});
