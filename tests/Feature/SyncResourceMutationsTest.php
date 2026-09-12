<?php

use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutProgram;

test('guests cannot sync offline resource mutations', function () {
    $this->postJson(route('sync.mutations'), ['actions' => []])->assertUnauthorized();
});

test('it creates a program offline and returns its real id', function () {
    $user = User::factory()->create();

    $actions = [
        [
            'uuid' => 'action-1',
            'type' => 'create_program',
            'client_uuid' => 'client-program-uuid-1',
            'occurred_at' => now()->toISOString(),
            'name' => 'Programa Offline',
            'description' => 'Criado sem internet',
        ],
    ];

    $response = $this->actingAs($user)
        ->postJson(route('sync.mutations'), ['actions' => $actions])
        ->assertOk();

    $this->assertDatabaseHas('workout_programs', [
        'user_id' => $user->id,
        'name' => 'Programa Offline',
        'client_uuid' => 'client-program-uuid-1',
    ]);

    $program = WorkoutProgram::where('client_uuid', 'client-program-uuid-1')->firstOrFail();

    $response->assertJson(['results' => [
        ['uuid' => 'action-1', 'type' => 'create_program', 'client_uuid' => 'client-program-uuid-1', 'id' => $program->id, 'status' => 'synced'],
    ]]);
});

test('resending the same create_program batch is idempotent', function () {
    $user = User::factory()->create();

    $actions = [[
        'uuid' => 'action-1',
        'type' => 'create_program',
        'client_uuid' => 'client-program-uuid-retry',
        'occurred_at' => now()->toISOString(),
        'name' => 'Programa Retry',
    ]];

    $this->actingAs($user)->postJson(route('sync.mutations'), ['actions' => $actions])->assertOk();
    $this->actingAs($user)->postJson(route('sync.mutations'), ['actions' => $actions])->assertOk();

    expect(WorkoutProgram::where('client_uuid', 'client-program-uuid-retry')->count())->toBe(1);
});

test('it creates a workout offline and links it to a program created in the same offline batch', function () {
    $user = User::factory()->create();

    $actions = [
        [
            'uuid' => 'action-program',
            'type' => 'create_program',
            'client_uuid' => 'client-program-uuid-2',
            'occurred_at' => now()->subMinute()->toISOString(),
            'name' => 'Programa Offline 2',
        ],
        [
            'uuid' => 'action-workout',
            'type' => 'create_workout',
            'client_uuid' => 'client-workout-uuid-1',
            'occurred_at' => now()->toISOString(),
            'name' => 'Treino Offline',
            'workout_program_id' => 'tmp_client-program-uuid-2',
        ],
    ];

    $this->actingAs($user)
        ->postJson(route('sync.mutations'), ['actions' => $actions])
        ->assertOk();

    $program = WorkoutProgram::where('client_uuid', 'client-program-uuid-2')->firstOrFail();
    $workout = Workout::where('client_uuid', 'client-workout-uuid-1')->firstOrFail();

    expect($workout->workout_program_id)->toBe($program->id);
    expect($workout->programs()->pluck('workout_programs.id')->all())->toContain($program->id);
});

test('it processes update, archive and delete actions against a program created online', function () {
    $user = User::factory()->create();
    $program = $user->workoutPrograms()->create(['name' => 'Original', 'is_active' => true]);

    $actions = [
        [
            'uuid' => 'action-update',
            'type' => 'update_program',
            'occurred_at' => now()->subMinutes(3)->toISOString(),
            'program_id' => $program->id,
            'name' => 'Renomeado Offline',
        ],
        [
            'uuid' => 'action-archive',
            'type' => 'archive_program',
            'occurred_at' => now()->subMinutes(2)->toISOString(),
            'program_id' => $program->id,
        ],
    ];

    $this->actingAs($user)
        ->postJson(route('sync.mutations'), ['actions' => $actions])
        ->assertOk();

    $program->refresh();
    expect($program->name)->toBe('Renomeado Offline');
    expect($program->is_active)->toBeFalse();
});

test('a user cannot sync resource mutations against another users program', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $program = $owner->workoutPrograms()->create(['name' => 'Alheio', 'is_active' => true]);

    $actions = [[
        'uuid' => 'action-1',
        'type' => 'archive_program',
        'occurred_at' => now()->toISOString(),
        'program_id' => $program->id,
    ]];

    $this->actingAs($intruder)
        ->postJson(route('sync.mutations'), ['actions' => $actions])
        ->assertStatus(422);
});

test('it attaches and detaches a workout to a program offline', function () {
    $user = User::factory()->create();
    $program = $user->workoutPrograms()->create(['name' => 'Programa', 'is_active' => true]);
    $workout = $user->workouts()->create(['name' => 'Treino']);

    $attach = [[
        'uuid' => 'action-attach',
        'type' => 'attach_workout',
        'occurred_at' => now()->subMinute()->toISOString(),
        'program_id' => $program->id,
        'workout_id' => $workout->id,
    ]];

    $this->actingAs($user)->postJson(route('sync.mutations'), ['actions' => $attach])->assertOk();
    $this->assertDatabaseHas('program_workouts', ['workout_program_id' => $program->id, 'workout_id' => $workout->id]);

    $detach = [[
        'uuid' => 'action-detach',
        'type' => 'detach_workout',
        'occurred_at' => now()->toISOString(),
        'program_id' => $program->id,
        'workout_id' => $workout->id,
    ]];

    $this->actingAs($user)->postJson(route('sync.mutations'), ['actions' => $detach])->assertOk();
    $this->assertDatabaseMissing('program_workouts', ['workout_program_id' => $program->id, 'workout_id' => $workout->id]);
});

test('it deletes a workout created offline within the same batch', function () {
    $user = User::factory()->create();

    $actions = [
        [
            'uuid' => 'action-create',
            'type' => 'create_workout',
            'client_uuid' => 'client-workout-uuid-delete',
            'occurred_at' => now()->subMinute()->toISOString(),
            'name' => 'Treino a ser excluído',
        ],
        [
            'uuid' => 'action-delete',
            'type' => 'delete_workout',
            'occurred_at' => now()->toISOString(),
            'workout_id' => 'tmp_client-workout-uuid-delete',
        ],
    ];

    $this->actingAs($user)
        ->postJson(route('sync.mutations'), ['actions' => $actions])
        ->assertOk();

    $this->assertDatabaseMissing('workouts', ['client_uuid' => 'client-workout-uuid-delete']);
});
