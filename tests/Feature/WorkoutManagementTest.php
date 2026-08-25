<?php

use App\Enums\MuscleGroup;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;

test('guests are redirected to login when archiving a workout', function () {
    $workout = Workout::factory()->create();

    $this->patch(route('workouts.archive', $workout))
        ->assertRedirect(route('login'));
});

test('guests are redirected to login when deleting a workout', function () {
    $workout = Workout::factory()->create();

    $this->delete(route('workouts.destroy', $workout))
        ->assertRedirect(route('login'));
});

test('user cannot archive another users workout', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $workout = Workout::factory()->for($user2)->create();

    $this->actingAs($user1)
        ->patch(route('workouts.archive', $workout))
        ->assertForbidden();
});

test('user cannot delete another users workout', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $workout = Workout::factory()->for($user2)->create();

    $this->actingAs($user1)
        ->delete(route('workouts.destroy', $workout))
        ->assertForbidden();

    $this->assertDatabaseHas('workouts', ['id' => $workout->id]);
});

test('a new workout is active by default', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->for($user)->create();

    expect($workout->is_active)->toBeTrue();
});

test('user can archive an active workout', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->for($user)->create(['is_active' => true]);

    $response = $this->actingAs($user)->patch(route('workouts.archive', $workout));

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('workouts', ['id' => $workout->id, 'is_active' => false]);
});

test('user can reactivate an archived workout', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->for($user)->create(['is_active' => false]);

    $response = $this->actingAs($user)->patch(route('workouts.archive', $workout));

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('workouts', ['id' => $workout->id, 'is_active' => true]);
});

test('the active and archived scopes only return workouts in the matching state', function () {
    $user = User::factory()->create();
    $active = Workout::factory()->for($user)->create(['is_active' => true]);
    $archived = Workout::factory()->for($user)->create(['is_active' => false]);

    expect($user->workouts()->active()->pluck('id')->all())->toBe([$active->id]);
    expect($user->workouts()->archived()->pluck('id')->all())->toBe([$archived->id]);
});

test('user can delete their own workout', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->for($user)->create();

    $exercise = Exercise::query()->create([
        'user_id' => null,
        'name' => 'Agachamento Livre',
        'primary_muscle_group' => MuscleGroup::Quads,
        'secondary_muscle_groups' => [],
    ]);

    $workout->workoutExercises()->create([
        'exercise_id' => $exercise->id,
        'order' => 0,
        'target_sets' => 3,
        'target_reps' => '10',
    ]);

    $response = $this->actingAs($user)->delete(route('workouts.destroy', $workout));

    $response->assertRedirect(route('workouts.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseMissing('workouts', ['id' => $workout->id]);
    $this->assertDatabaseMissing('workout_exercises', ['workout_id' => $workout->id]);
});

test('deleting a workout preserves its past sessions but orphans them', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->for($user)->create();

    $session = $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now()->subDay(),
        'completed_at' => now()->subDay(),
    ]);

    $this->actingAs($user)->delete(route('workouts.destroy', $workout));

    $this->assertDatabaseHas('workout_sessions', ['id' => $session->id, 'workout_id' => null]);
});
