<?php

use App\Models\User;
use App\Models\WorkoutSession;
use App\Services\WorkoutSessionService;

test('finishing a workout session removes the activeWorkoutSession prop entirely', function () {
    $user = User::factory()->create();
    $workout = $user->workouts()->create(['name' => 'Treino A']);
    $session = $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now()->subMinutes(20),
    ]);

    $this->actingAs($user)
        ->post(route('workout-sessions.finish', $session))
        ->assertRedirect(route('dashboard'));

    $session->refresh();
    expect($session->completed_at)->not->toBeNull();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('activeWorkoutSession', null));
});

test('finishing a session also closes any other ghost session left open for the same user', function () {
    $user = User::factory()->create();
    $workout = $user->workouts()->create(['name' => 'Treino A']);

    // Sessão fantasma que ficou aberta por um bug/teste anterior.
    $ghostSession = $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now()->subDays(3),
    ]);

    $currentSession = $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now()->subMinutes(10),
    ]);

    $this->actingAs($user)
        ->post(route('workout-sessions.finish', $currentSession))
        ->assertRedirect(route('dashboard'));

    $ghostSession->refresh();
    $currentSession->refresh();

    expect($ghostSession->completed_at)->not->toBeNull();
    expect($currentSession->completed_at)->not->toBeNull();
});

test('starting a new workout session automatically closes any previously open session', function () {
    $user = User::factory()->create();
    $workoutA = $user->workouts()->create(['name' => 'Treino A']);
    $workoutB = $user->workouts()->create(['name' => 'Treino B']);

    $oldSession = $user->workoutSessions()->create([
        'workout_id' => $workoutA->id,
        'started_at' => now()->subHour(),
    ]);

    $response = $this->actingAs($user)->post(route('workouts.start', $workoutB));

    $oldSession->refresh();
    expect($oldSession->completed_at)->not->toBeNull();

    $newSession = WorkoutSession::where('workout_id', $workoutB->id)->firstOrFail();
    expect($newSession->completed_at)->toBeNull();

    $response->assertRedirect(route('workout-sessions.show', $newSession));

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->where('activeWorkoutSession.id', $newSession->id)
        );
});

test('the ghost session cleanup service closes every open session in the database', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $workoutA = $userA->workouts()->create(['name' => 'Treino A']);
    $workoutB = $userB->workouts()->create(['name' => 'Treino B']);

    $ghostA = $userA->workoutSessions()->create([
        'workout_id' => $workoutA->id,
        'started_at' => now()->subWeek(),
    ]);
    $ghostB = $userB->workoutSessions()->create([
        'workout_id' => $workoutB->id,
        'started_at' => now()->subWeek(),
    ]);

    app(WorkoutSessionService::class)->closeAllGhostSessions();

    expect($ghostA->refresh()->completed_at)->not->toBeNull();
    expect($ghostB->refresh()->completed_at)->not->toBeNull();
});

test('the sessions:close-ghosts artisan command closes all open sessions', function () {
    $user = User::factory()->create();
    $workout = $user->workouts()->create(['name' => 'Treino A']);

    $ghost = $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now()->subWeek(),
    ]);

    $this->artisan('sessions:close-ghosts')->assertSuccessful();

    expect($ghost->refresh()->completed_at)->not->toBeNull();
});
