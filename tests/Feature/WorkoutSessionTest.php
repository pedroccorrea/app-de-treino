<?php

use App\Models\Exercise;
use App\Models\SetLog;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSession;
use Database\Seeders\ExerciseSeeder;

test('guests are redirected to login when accessing workout sessions', function () {
    $session = WorkoutSession::factory()->create();

    $this->get(route('workout-sessions.show', $session))->assertRedirect(route('login'));
    $this->post(route('workout-sessions.finish', $session))->assertRedirect(route('login'));
    $this->post(route('workout-sessions.sets.store', $session))->assertRedirect(route('login'));
});

test('authenticated user can start a workout session', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();

    $workout = $user->workouts()->create(['name' => 'Treino A']);
    $workout->workoutExercises()->create([
        'exercise_id' => $exercise->id,
        'order' => 0,
        'target_sets' => 3,
        'target_reps' => '10',
        'rest_seconds' => 60,
    ]);

    $response = $this->actingAs($user)->post(route('workouts.start', $workout));

    $session = WorkoutSession::where('user_id', $user->id)->first();
    expect($session)->not->toBeNull();
    expect($session->workout_id)->toBe($workout->id);
    expect($session->completed_at)->toBeNull();

    $response->assertRedirect(route('workout-sessions.show', $session));
});

test('user cannot start another users workout', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $workout = $user2->workouts()->create(['name' => 'Treino Alheio']);

    $this->actingAs($user1)
        ->post(route('workouts.start', $workout))
        ->assertForbidden();
});

test('authenticated user can view active workout session page', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();

    $workout = $user->workouts()->create(['name' => 'Treino Peito']);
    $workout->workoutExercises()->create([
        'exercise_id' => $exercise->id,
        'order' => 0,
        'target_sets' => 4,
        'target_reps' => '8-12',
    ]);

    $session = $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('workout-sessions.show', $session))
        ->assertOk()
        ->assertInertia(
            fn($page) => $page
                ->component('WorkoutSessions/Active')
                ->has('session')
                ->has('workout')
                ->has('exercises')
                ->has('setLogs')
                ->has('lastLogs')
        );
});

test('authenticated user can log and update sets in a session', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();

    $workout = $user->workouts()->create(['name' => 'Treino A']);
    $session = $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now(),
    ]);

    // Gravar 1ª série
    $response = $this->actingAs($user)->post(route('workout-sessions.sets.store', $session), [
        'exercise_id' => $exercise->id,
        'set_number' => 1,
        'weight' => 50.5,
        'reps' => 10,
        'rpe' => 8,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('set_logs', [
        'workout_session_id' => $session->id,
        'exercise_id' => $exercise->id,
        'set_number' => 1,
        'weight' => 50.5,
        'reps' => 10,
        'rpe' => 8,
    ]);

    // Atualizar a mesma série (peso modificado)
    $this->actingAs($user)->post(route('workout-sessions.sets.store', $session), [
        'exercise_id' => $exercise->id,
        'set_number' => 1,
        'weight' => 52.0,
        'reps' => 10,
        'rpe' => 8.5,
    ]);

    expect(SetLog::where('workout_session_id', $session->id)->count())->toBe(1);
    $this->assertDatabaseHas('set_logs', [
        'workout_session_id' => $session->id,
        'exercise_id' => $exercise->id,
        'set_number' => 1,
        'weight' => 52.0,
    ]);
});

test('authenticated user can finish a workout session', function () {
    $user = User::factory()->create();
    $workout = $user->workouts()->create(['name' => 'Treino A']);
    $session = $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now()->subMinutes(45),
    ]);

    $response = $this->actingAs($user)->post(route('workout-sessions.finish', $session));

    $response->assertRedirect(route('dashboard'));
    $session->refresh();
    expect($session->completed_at)->not->toBeNull();
});
