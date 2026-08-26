<?php

use App\Enums\MuscleGroup;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutProgram;

test('guests are redirected to login for every program route', function () {
    $program = WorkoutProgram::factory()->create();

    $this->get(route('programs.index'))->assertRedirect(route('login'));
    $this->post(route('programs.store'))->assertRedirect(route('login'));
    $this->get(route('programs.show', $program))->assertRedirect(route('login'));
    $this->put(route('programs.update', $program))->assertRedirect(route('login'));
    $this->patch(route('programs.activate', $program))->assertRedirect(route('login'));
    $this->patch(route('programs.archive', $program))->assertRedirect(route('login'));
    $this->delete(route('programs.destroy', $program))->assertRedirect(route('login'));
});

test('authenticated user can view their programs list', function () {
    $user = User::factory()->create();
    WorkoutProgram::factory()->for($user)->create(['is_active' => true]);

    $this->actingAs($user)
        ->get(route('programs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Programs/Index')->has('programs', 1));
});

test('the first program a user creates becomes active automatically', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('programs.store'), [
        'name' => 'Hipertrofia ABCD',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('workout_programs', [
        'user_id' => $user->id,
        'name' => 'Hipertrofia ABCD',
        'is_active' => true,
    ]);
});

test('a second program is created inactive so exclusivity is preserved', function () {
    $user = User::factory()->create();
    WorkoutProgram::factory()->for($user)->create(['name' => 'Programa 1', 'is_active' => true]);

    $this->actingAs($user)->post(route('programs.store'), [
        'name' => 'Programa 2',
    ]);

    $this->assertDatabaseHas('workout_programs', [
        'user_id' => $user->id,
        'name' => 'Programa 1',
        'is_active' => true,
    ]);
    $this->assertDatabaseHas('workout_programs', [
        'user_id' => $user->id,
        'name' => 'Programa 2',
        'is_active' => false,
    ]);
});

test('program creation requires a name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('programs.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('activating a program archives whichever program was previously active', function () {
    $user = User::factory()->create();
    $current = WorkoutProgram::factory()->for($user)->create(['is_active' => true]);
    $next = WorkoutProgram::factory()->for($user)->create(['is_active' => false]);

    $response = $this->actingAs($user)->patch(route('programs.activate', $next));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('workout_programs', ['id' => $next->id, 'is_active' => true]);
    expect($next->fresh()->archived_at)->toBeNull();

    $current->refresh();
    expect($current->is_active)->toBeFalse();
    expect($current->archived_at)->not->toBeNull();
});

test('activating a program does not disturb other users programs', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $user1Active = WorkoutProgram::factory()->for($user1)->create(['is_active' => true]);
    $user2Active = WorkoutProgram::factory()->for($user2)->create(['is_active' => true]);
    $user1New = WorkoutProgram::factory()->for($user1)->create(['is_active' => false]);

    $this->actingAs($user1)->patch(route('programs.activate', $user1New));

    expect($user1Active->fresh()->is_active)->toBeFalse();
    expect($user2Active->fresh()->is_active)->toBeTrue();
});

test('user cannot activate another users program', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $program = WorkoutProgram::factory()->for($user2)->create();

    $this->actingAs($user1)
        ->patch(route('programs.activate', $program))
        ->assertForbidden();
});

test('user can archive their active program', function () {
    $user = User::factory()->create();
    $program = WorkoutProgram::factory()->for($user)->create(['is_active' => true]);

    $response = $this->actingAs($user)->patch(route('programs.archive', $program));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $program->refresh();
    expect($program->is_active)->toBeFalse();
    expect($program->archived_at)->not->toBeNull();
});

test('user cannot archive another users program', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $program = WorkoutProgram::factory()->for($user2)->create();

    $this->actingAs($user1)
        ->patch(route('programs.archive', $program))
        ->assertForbidden();
});

test('deleting a program cascades to its workouts and their exercises', function () {
    $user = User::factory()->create();
    $program = WorkoutProgram::factory()->for($user)->create(['is_active' => true]);
    $workout = Workout::factory()->for($user)->create(['workout_program_id' => $program->id]);

    $exercise = Exercise::query()->create([
        'user_id' => null,
        'name' => 'Supino Reto',
        'primary_muscle_group' => MuscleGroup::Chest,
        'secondary_muscle_groups' => [],
    ]);

    $workout->workoutExercises()->create([
        'exercise_id' => $exercise->id,
        'order' => 0,
        'target_sets' => 3,
        'target_reps' => '10',
    ]);

    $response = $this->actingAs($user)->delete(route('programs.destroy', $program));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseMissing('workout_programs', ['id' => $program->id]);
    $this->assertDatabaseMissing('workouts', ['id' => $workout->id]);
    $this->assertDatabaseMissing('workout_exercises', ['workout_id' => $workout->id]);
});

test('authenticated user can view a programs details with its workouts', function () {
    $user = User::factory()->create();
    $program = WorkoutProgram::factory()->for($user)->create([
        'name' => 'Hipertrofia ABCD',
        'description' => 'Foco em ganho de massa',
        'is_active' => true,
    ]);
    $workout = Workout::factory()->for($user)->create([
        'workout_program_id' => $program->id,
        'name' => 'Treino A',
    ]);
    $program->workouts()->attach($workout->id);

    $exercise = Exercise::query()->create([
        'user_id' => null,
        'name' => 'Supino Reto',
        'primary_muscle_group' => MuscleGroup::Chest,
        'secondary_muscle_groups' => [],
    ]);

    $workout->workoutExercises()->create([
        'exercise_id' => $exercise->id,
        'order' => 0,
        'target_sets' => 3,
        'target_reps' => '10',
    ]);

    $this->actingAs($user)
        ->get(route('programs.show', $program))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Programs/Show')
                ->where('program.id', $program->id)
                ->where('program.name', 'Hipertrofia ABCD')
                ->where('program.description', 'Foco em ganho de massa')
                ->has('program.workouts', 1)
                ->where('program.workouts.0.id', $workout->id)
                ->where('program.workouts.0.name', 'Treino A')
                ->where('program.workouts.0.exercises_count', 1)
        );
});

test('user cannot view another users program details', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $program = WorkoutProgram::factory()->for($user2)->create();

    $this->actingAs($user1)
        ->get(route('programs.show', $program))
        ->assertForbidden();
});

test('user can update a programs name and description', function () {
    $user = User::factory()->create();
    $program = WorkoutProgram::factory()->for($user)->create([
        'name' => 'Nome Antigo',
        'description' => 'Descrição antiga',
    ]);

    $response = $this->actingAs($user)->put(route('programs.update', $program), [
        'name' => 'Nome Novo',
        'description' => 'Descrição nova',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('workout_programs', [
        'id' => $program->id,
        'name' => 'Nome Novo',
        'description' => 'Descrição nova',
    ]);
});

test('updating a program requires a name', function () {
    $user = User::factory()->create();
    $program = WorkoutProgram::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('programs.update', $program), ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('user cannot update another users program', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $program = WorkoutProgram::factory()->for($user2)->create(['name' => 'Original']);

    $this->actingAs($user1)
        ->put(route('programs.update', $program), ['name' => 'Hackeado'])
        ->assertForbidden();

    $this->assertDatabaseHas('workout_programs', ['id' => $program->id, 'name' => 'Original']);
});

test('user cannot delete another users program', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $program = WorkoutProgram::factory()->for($user2)->create();

    $this->actingAs($user1)
        ->delete(route('programs.destroy', $program))
        ->assertForbidden();

    $this->assertDatabaseHas('workout_programs', ['id' => $program->id]);
});

test('creating a workout without an explicit program links it to the active program', function () {
    $user = User::factory()->create();
    $program = WorkoutProgram::factory()->for($user)->create(['is_active' => true]);

    $this->actingAs($user)->post(route('workouts.store'), [
        'name' => 'Treino A',
    ]);

    $this->assertDatabaseHas('workouts', [
        'user_id' => $user->id,
        'name' => 'Treino A',
        'workout_program_id' => $program->id,
    ]);
});

test('creating a workout with an explicit program links it to that program even when it is not active', function () {
    $user = User::factory()->create();
    WorkoutProgram::factory()->for($user)->create(['is_active' => true]);
    $archivedProgram = WorkoutProgram::factory()->for($user)->create([
        'is_active' => false,
        'archived_at' => now(),
    ]);

    $this->actingAs($user)->post(route('workouts.store'), [
        'name' => 'Treino do Programa Arquivado',
        'workout_program_id' => $archivedProgram->id,
    ]);

    $this->assertDatabaseHas('workouts', [
        'user_id' => $user->id,
        'name' => 'Treino do Programa Arquivado',
        'workout_program_id' => $archivedProgram->id,
    ]);
});

test('user cannot create a workout linked to another users program', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $otherProgram = WorkoutProgram::factory()->for($user2)->create();

    $this->actingAs($user1)
        ->post(route('workouts.store'), [
            'name' => 'Treino Invasor',
            'workout_program_id' => $otherProgram->id,
        ])
        ->assertSessionHasErrors('workout_program_id');
});

test('the workouts index page exposes only the active program', function () {
    $user = User::factory()->create();
    $active = WorkoutProgram::factory()->for($user)->create(['name' => 'Programa Atual', 'is_active' => true]);
    WorkoutProgram::factory()->for($user)->create([
        'name' => 'Programa Antigo',
        'is_active' => false,
        'archived_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('workouts.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Workouts/Index')
                ->where('activeProgram.id', $active->id)
                ->where('activeProgram.name', 'Programa Atual')
                ->missing('archivedPrograms')
        );
});
