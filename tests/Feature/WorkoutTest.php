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
        ->assertInertia(fn ($page) => $page->component('Workouts/Index'));
});

test('authenticated user can view create workout page with catalog', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('workouts.create'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
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

test('authenticated user can view edit workout page with catalog', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $workout = $user->workouts()->create(['name' => 'Treino Editável']);

    $this->actingAs($user)
        ->get(route('workouts.edit', $workout))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Workouts/Edit')
                ->has('workout')
                ->has('exercisesCatalog')
        );
});

test('user cannot view edit page of another users workout', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $workout = $user2->workouts()->create(['name' => 'Treino Alheio']);

    $this->actingAs($user1)
        ->get(route('workouts.edit', $workout))
        ->assertForbidden();
});

test('authenticated user can update their workout data and exercises', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercises = Exercise::limit(3)->get();

    $workout = $user->workouts()->create(['name' => 'Treino Original']);
    $workout->workoutExercises()->create([
        'exercise_id' => $exercises[0]->id,
        'order' => 0,
        'target_sets' => 3,
        'target_reps' => '10',
    ]);

    $response = $this->actingAs($user)->put(route('workouts.update', $workout), [
        'name' => 'Treino Atualizado',
        'description' => 'Nova descrição',
        'days_of_week' => [1, 4],
        'exercises' => [
            [
                'id' => $exercises[1]->id,
                'target_sets' => 4,
                'target_reps' => '8-12',
                'order' => 0,
            ],
            [
                'id' => $exercises[2]->id,
                'target_sets' => 5,
                'target_reps' => '6',
                'order' => 1,
            ],
        ],
    ]);

    // With no `return_to`, the user lands on the workout's own page.
    $response->assertRedirect(route('workouts.show', $workout));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('workouts', [
        'id' => $workout->id,
        'name' => 'Treino Atualizado',
        'description' => 'Nova descrição',
    ]);

    // The exercise removed from the payload should no longer be in the pivot table
    $this->assertDatabaseMissing('workout_exercises', [
        'workout_id' => $workout->id,
        'exercise_id' => $exercises[0]->id,
    ]);

    $this->assertDatabaseHas('workout_exercises', [
        'workout_id' => $workout->id,
        'exercise_id' => $exercises[1]->id,
        'target_sets' => 4,
        'target_reps' => '8-12',
        'order' => 0,
    ]);
});

test('updating a workout redirects to the given return_to path', function () {
    $user = User::factory()->create();
    $workout = $user->workouts()->create(['name' => 'Treino Original']);

    $response = $this->actingAs($user)->put(route('workouts.update', ['workout' => $workout, 'return_to' => '/workouts']), [
        'name' => 'Treino Atualizado',
    ]);

    $response->assertRedirect('/workouts');
});

test('updating a workout ignores an unsafe return_to and falls back to the show page', function () {
    $user = User::factory()->create();
    $workout = $user->workouts()->create(['name' => 'Treino Original']);

    $response = $this->actingAs($user)->put(route('workouts.update', ['workout' => $workout, 'return_to' => 'https://evil.example.com']), [
        'name' => 'Treino Atualizado',
    ]);

    $response->assertRedirect(route('workouts.show', $workout));
});

test('user cannot update another users workout', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $workout = $user2->workouts()->create(['name' => 'Treino Alheio']);

    $this->actingAs($user1)
        ->put(route('workouts.update', $workout), ['name' => 'Hackeado'])
        ->assertForbidden();
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
            'order' => $i,
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
            'workout_id' => $workout->id,
            'exercise_id' => $exerciseId,
            'order' => $newPosition,
        ]);
    }
});
