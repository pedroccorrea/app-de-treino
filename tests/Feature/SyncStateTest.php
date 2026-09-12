<?php

use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutProgram;
use Database\Seeders\ExerciseSeeder;

test('guests cannot fetch the full offline sync state', function () {
    $this->getJson(route('sync.state'))->assertUnauthorized();
});

test('it returns the full state needed for offline-first navigation', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();

    $program = $user->workoutPrograms()->create(['name' => 'Programa A', 'is_active' => true]);
    $workout = $user->workouts()->create(['name' => 'Treino A', 'workout_program_id' => $program->id]);
    $workout->programs()->syncWithoutDetaching([$program->id]);
    $workout->workoutExercises()->create(['exercise_id' => $exercise->id, 'order' => 0, 'target_sets' => 3, 'target_reps' => '10']);

    $response = $this->actingAs($user)->getJson(route('sync.state'))->assertOk();

    $response->assertJsonStructure([
        'version',
        'programs' => [['id', 'name', 'is_active', 'workouts_count', 'workouts' => [['id', 'name', 'description', 'exercises_count']]]],
        'workouts' => [['id', 'name', 'exercises_count', 'exercises', 'program_ids']],
        'exercisesCatalog',
        'lastLogs',
        'syncedAt',
    ]);

    expect($response->json('programs.0.name'))->toBe('Programa A');
    expect($response->json('workouts.0.name'))->toBe('Treino A');
    expect($response->json('workouts.0.program_ids'))->toContain($program->id);

    // O treino aninhado dentro de cada programa precisa trazer `exercises_count`
    // (não só `id`/`name`): é esse formato que alimenta tanto a tela
    // `Programs/Index` quanto `Programs/Show` offline (`ProgramWorkoutCard.vue`
    // exibe a contagem de exercícios de cada ficha vinculada).
    expect($response->json('programs.0.workouts.0.exercises_count'))->toBe(1);
});

test('it never leaks another users programs or workouts', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    WorkoutProgram::query()->create(['user_id' => $owner->id, 'name' => 'Programa Alheio', 'is_active' => true]);

    $response = $this->actingAs($intruder)->getJson(route('sync.state'))->assertOk();

    expect($response->json('programs'))->toBeEmpty();
    expect($response->json('workouts'))->toBeEmpty();
});
