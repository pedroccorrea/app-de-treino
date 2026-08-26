<?php

use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutProgram;

test('guests are redirected to login for the attach and detach routes', function () {
    $program = WorkoutProgram::factory()->create();
    $workout = Workout::factory()->create();

    $this->post(route('programs.workouts.attach', $program))->assertRedirect(route('login'));
    $this->delete(route('programs.workouts.detach', [$program, $workout]))->assertRedirect(route('login'));
});

test('user can link multiple workouts to a program at once', function () {
    $user = User::factory()->create();
    $program = WorkoutProgram::factory()->for($user)->create();
    $workoutA = Workout::factory()->for($user)->create();
    $workoutB = Workout::factory()->for($user)->create();

    $response = $this->actingAs($user)->post(route('programs.workouts.attach', $program), [
        'workout_ids' => [$workoutA->id, $workoutB->id],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('program_workouts', [
        'workout_program_id' => $program->id,
        'workout_id' => $workoutA->id,
    ]);
    $this->assertDatabaseHas('program_workouts', [
        'workout_program_id' => $program->id,
        'workout_id' => $workoutB->id,
    ]);

    expect($program->workouts()->count())->toBe(2);
});

test('attaching an already linked workout does not duplicate the pivot row', function () {
    $user = User::factory()->create();
    $program = WorkoutProgram::factory()->for($user)->create();
    $workout = Workout::factory()->for($user)->create();

    $program->workouts()->attach($workout->id);

    $this->actingAs($user)->post(route('programs.workouts.attach', $program), [
        'workout_ids' => [$workout->id],
    ]);

    $this->assertDatabaseCount('program_workouts', 1);
});

test('attaching requires at least one workout id', function () {
    $user = User::factory()->create();
    $program = WorkoutProgram::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('programs.workouts.attach', $program), ['workout_ids' => []])
        ->assertSessionHasErrors('workout_ids');
});

test('user cannot attach a workout owned by another user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $program = WorkoutProgram::factory()->for($user)->create();
    $foreignWorkout = Workout::factory()->for($otherUser)->create();

    $this->actingAs($user)
        ->post(route('programs.workouts.attach', $program), [
            'workout_ids' => [$foreignWorkout->id],
        ])
        ->assertSessionHasErrors('workout_ids.0');

    $this->assertDatabaseMissing('program_workouts', [
        'workout_program_id' => $program->id,
        'workout_id' => $foreignWorkout->id,
    ]);
});

test('user cannot attach workouts to another users program', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $program = WorkoutProgram::factory()->for($otherUser)->create();
    $workout = Workout::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('programs.workouts.attach', $program), [
            'workout_ids' => [$workout->id],
        ])
        ->assertForbidden();
});

test('detaching a workout unlinks it from the program without deleting it', function () {
    $user = User::factory()->create();
    $program = WorkoutProgram::factory()->for($user)->create();
    $workout = Workout::factory()->for($user)->create();

    $program->workouts()->attach($workout->id);

    $response = $this->actingAs($user)->delete(route('programs.workouts.detach', [$program, $workout]));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseMissing('program_workouts', [
        'workout_program_id' => $program->id,
        'workout_id' => $workout->id,
    ]);
    $this->assertDatabaseHas('workouts', ['id' => $workout->id]);
});

test('user cannot detach a workout from another users program', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $program = WorkoutProgram::factory()->for($otherUser)->create();
    $workout = Workout::factory()->for($otherUser)->create();
    $program->workouts()->attach($workout->id);

    $this->actingAs($user)
        ->delete(route('programs.workouts.detach', [$program, $workout]))
        ->assertForbidden();

    $this->assertDatabaseHas('program_workouts', [
        'workout_program_id' => $program->id,
        'workout_id' => $workout->id,
    ]);
});

test('a workout can belong to multiple programs at once', function () {
    $user = User::factory()->create();
    $programA = WorkoutProgram::factory()->for($user)->create(['name' => 'Programa A']);
    $programB = WorkoutProgram::factory()->for($user)->create(['name' => 'Programa B']);
    $workout = Workout::factory()->for($user)->create();

    $this->actingAs($user)->post(route('programs.workouts.attach', $programA), [
        'workout_ids' => [$workout->id],
    ]);
    $this->actingAs($user)->post(route('programs.workouts.attach', $programB), [
        'workout_ids' => [$workout->id],
    ]);

    expect($workout->programs()->pluck('workout_programs.id')->sort()->values()->all())
        ->toBe(collect([$programA->id, $programB->id])->sort()->values()->all());

    $this->assertDatabaseHas('program_workouts', ['workout_program_id' => $programA->id, 'workout_id' => $workout->id]);
    $this->assertDatabaseHas('program_workouts', ['workout_program_id' => $programB->id, 'workout_id' => $workout->id]);
});

test('the program detail page lists all its linked workouts', function () {
    $user = User::factory()->create();
    $program = WorkoutProgram::factory()->for($user)->create();
    $workoutA = Workout::factory()->for($user)->create(['name' => 'Treino A']);
    $workoutB = Workout::factory()->for($user)->create(['name' => 'Treino B']);

    $program->workouts()->attach([$workoutA->id, $workoutB->id]);

    $this->actingAs($user)
        ->get(route('programs.show', $program))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Programs/Show')
            ->has('program.workouts', 2)
        );
});

test('the program detail page exposes workouts not yet linked for the picker', function () {
    $user = User::factory()->create();
    $program = WorkoutProgram::factory()->for($user)->create();
    $linked = Workout::factory()->for($user)->create(['name' => 'Já vinculado']);
    $unlinked = Workout::factory()->for($user)->create(['name' => 'Ainda solto']);

    $program->workouts()->attach($linked->id);

    $this->actingAs($user)
        ->get(route('programs.show', $program))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Programs/Show')
            ->has('availableWorkouts', 1)
            ->where('availableWorkouts.0.id', $unlinked->id)
        );
});
