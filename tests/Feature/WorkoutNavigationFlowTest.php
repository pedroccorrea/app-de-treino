<?php

use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutProgram;
use Database\Seeders\ExerciseSeeder;

test('creating a workout linked to a program redirects back to the program page', function () {
    $user = User::factory()->create();
    $program = WorkoutProgram::factory()->for($user)->create();

    $response = $this->actingAs($user)->post(route('workouts.store'), [
        'name' => 'Treino A',
        'workout_program_id' => $program->id,
    ]);

    $response->assertRedirect(route('programs.show', $program->id));
});

test('creating a workout linked to a program honors an explicit return_to', function () {
    $user = User::factory()->create();
    $program = WorkoutProgram::factory()->for($user)->create();

    $response = $this->actingAs($user)->post(route('workouts.store', ['return_to' => '/programs/'.$program->id]), [
        'name' => 'Treino A',
        'workout_program_id' => $program->id,
    ]);

    $response->assertRedirect('/programs/'.$program->id);
});

test('creating a workout without a program still redirects to the workouts list', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('workouts.store'), [
        'name' => 'Treino Solto',
    ]);

    $response->assertRedirect(route('workouts.index'));
});

test('creating a workout without an explicit workout_program_id redirects to the workouts list even when the user has an active program', function () {
    $user = User::factory()->create();
    WorkoutProgram::factory()->for($user)->create(['is_active' => true]);

    $response = $this->actingAs($user)->post(route('workouts.store'), [
        'name' => 'Treino Solto',
    ]);

    $response->assertRedirect(route('workouts.index'));
});

test('updating a workout with return_to=/programs/1 redirects exactly to /programs/1', function () {
    $user = User::factory()->create();
    $workout = $user->workouts()->create(['name' => 'Treino Original']);

    $response = $this->actingAs($user)->put(route('workouts.update', ['workout' => $workout, 'return_to' => '/programs/1']), [
        'name' => 'Treino Atualizado',
    ]);

    $response->assertRedirect('/programs/1');
});

test('updating a workout without return_to redirects to the workout show page', function () {
    $user = User::factory()->create();
    $workout = $user->workouts()->create(['name' => 'Treino Original']);

    $response = $this->actingAs($user)->put(route('workouts.update', $workout), [
        'name' => 'Treino Atualizado',
    ]);

    $response->assertRedirect(route('workouts.show', $workout));
});

test('deleting a workout with return_to redirects exactly there', function () {
    $user = User::factory()->create();
    $program = WorkoutProgram::factory()->for($user)->create();
    $workout = $user->workouts()->create([
        'name' => 'Treino a Excluir',
        'workout_program_id' => $program->id,
    ]);

    $response = $this->actingAs($user)->delete(route('workouts.destroy', [
        'workout' => $workout,
        'return_to' => route('programs.show', $program->id),
    ]));

    $response->assertRedirect(route('programs.show', $program->id));
});

test('deleting a workout without return_to redirects to the workouts list', function () {
    $user = User::factory()->create();
    $workout = $user->workouts()->create(['name' => 'Treino a Excluir']);

    $response = $this->actingAs($user)->delete(route('workouts.destroy', $workout));

    $response->assertRedirect(route('workouts.index'));
});

test('exercise history page carries the active session URL forward as return_to, unmodified', function () {
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

    // This mirrors the link ExerciseActiveCard.vue builds: the exercise's
    // name links to its history page carrying the current (active session)
    // URL as return_to, so the history page's back button can bring the
    // user exactly back here — the invariant in .ai/rules/navigation-flow-invariants.md.
    $sessionUrl = route('workout-sessions.show', $session);

    $response = $this->actingAs($user)->get(route('exercises.history', [
        'exercise' => $exercise,
        'return_to' => $sessionUrl,
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Exercises/History'));

    expect($response->baseRequest->query('return_to'))->toBe($sessionUrl);
});

test('accessing a workout show page with return_to=/dashboard preserves it so the back button points to the dashboard', function () {
    $user = User::factory()->create();
    $workout = $user->workouts()->create(['name' => 'Treino Peito']);

    // Workouts/Show.vue's "Voltar" button reads the return_to query string
    // straight from window.location.search (the app-wide pattern — see
    // .ai/rules/navigation-flow-invariants.md #4). This asserts the
    // Dashboard's shortcut links to workouts.show land here with
    // return_to=/dashboard intact, so the back button sends the user home.
    $response = $this->actingAs($user)->get(route('workouts.show', [
        'workout' => $workout,
        'return_to' => '/dashboard',
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Workouts/Show'));

    expect($response->baseRequest->query('return_to'))->toBe('/dashboard');
});
