<?php

use App\Models\User;
use App\Models\WorkoutProgram;

test('creating a workout linked to a program redirects straight to its edit screen with a return_to back to the program', function () {
    $user = User::factory()->create();
    $program = WorkoutProgram::factory()->for($user)->create();

    $response = $this->actingAs($user)->post(route('workouts.store'), [
        'name' => 'Treino A',
        'workout_program_id' => $program->id,
    ]);

    $workout = $user->workouts()->where('name', 'Treino A')->firstOrFail();

    $response->assertRedirect(route('workouts.edit', [
        'workout' => $workout->id,
        'return_to' => route('programs.show', $program->id),
    ]));
});

test('creating a workout linked to a program honors an explicit return_to', function () {
    $user = User::factory()->create();
    $program = WorkoutProgram::factory()->for($user)->create();

    $response = $this->actingAs($user)->post(route('workouts.store', ['return_to' => '/programs/'.$program->id]), [
        'name' => 'Treino A',
        'workout_program_id' => $program->id,
    ]);

    $workout = $user->workouts()->where('name', 'Treino A')->firstOrFail();

    $response->assertRedirect(route('workouts.edit', [
        'workout' => $workout->id,
        'return_to' => '/programs/'.$program->id,
    ]));
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
