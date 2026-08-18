<?php

use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use Database\Seeders\ExerciseSeeder;

test('guests are redirected to login when accessing workouts', function () {
    $this->get(route('workouts.index'))->assertRedirect(route('login'));
    $this->get(route('workouts.create'))->assertRedirect(route('login'));
});

test('authenticated user can view workouts list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('workouts.index'))
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('Workouts/Index'));
});

test('authenticated user can view create workout page with catalog', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('workouts.create'))
        ->assertOk()
        ->assertInertia(
            fn($page) => $page
                ->component('Workouts/Create')
                ->has('exercisesCatalog')
        );
});

test('authenticated user can create a new workout with exercises', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();

    $response = $this->actingAs($user)->post(route('workouts.store'), [
        'name' => 'Treino A - Teste',
        'description' => 'Treino de teste',
        'exercises' => [
            [
                'id' => $exercise->id,
                'target_sets' => 4,
                'target_reps' => '10-12',
                'order' => 0,
            ],
        ],
    ]);

    $response->assertRedirect(route('workouts.index'));
    $this->assertDatabaseHas('workouts', [
        'user_id' => $user->id,
        'name' => 'Treino A - Teste',
    ]);
});

test('guests are redirected to login when reordering exercises', function () {
    $workout = Workout::factory()->create();

    $this->patch(route('workouts.reorder', $workout), ['exercise_ids' => []])
        ->assertRedirect(route('login'));
});

test('user cannot reorder exercises of another users workout', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $workout = $user2->workouts()->create(['name' => 'Treino Alheio']);

    $this->actingAs($user1)
        ->patch(route('workouts.reorder', $workout), ['exercise_ids' => []])
        ->assertForbidden();
});

test('user can reorder exercises in their workout', function () {
    $this->seed(ExerciseSeeder::class);

    $user = User::factory()->create();
    $exercises = Exercise::limit(3)->get();

    $workout = $user->workouts()->create(['name' => 'Treino Reordenável']);

    foreach ($exercises as $i => $exercise) {
        $workout->workoutExercises()->create([
            'exercise_id' => $exercise->id,
            'order'       => $i,
            'target_sets' => 3,
            'target_reps' => '10',
        ]);
    }

    // Reverse the order
    $reversedIds = $exercises->pluck('id')->reverse()->values()->all();

    $response = $this->actingAs($user)->patch(route('workouts.reorder', $workout), [
        'exercise_ids' => $reversedIds,
    ]);

    $response->assertRedirect();

    // Verify persisted order in the pivot table
    foreach ($reversedIds as $newPosition => $exerciseId) {
        $this->assertDatabaseHas('workout_exercises', [
            'workout_id'  => $workout->id,
            'exercise_id' => $exerciseId,
            'order'       => $newPosition,
        ]);
    }
});
