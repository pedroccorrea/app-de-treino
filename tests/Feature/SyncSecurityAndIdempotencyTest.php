<?php

use App\Models\Exercise;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use Database\Seeders\ExerciseSeeder;

test('the endpoint accepts an ISO 8601 payload with UUID identifiers straight from the browser without a 422', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();
    $workout = $user->workouts()->create(['name' => 'Treino ISO']);

    // Formato exato que `Date.prototype.toISOString()` produz no navegador
    // (milissegundos + sufixo `Z`) — e `weight`/`reps` como strings numéricas,
    // como um `<input>` de formulário costuma entregar.
    $actions = [
        [
            'uuid' => 'action-start',
            'type' => 'start_session',
            'client_session_id' => 'client-session-uuid-iso',
            'occurred_at' => '2026-09-17T14:30:10.123Z',
            'workout_id' => $workout->id,
        ],
        [
            'uuid' => 'action-set-1',
            'type' => 'log_set',
            'client_session_id' => 'client-session-uuid-iso',
            'client_set_id' => 'set-uuid-1',
            'occurred_at' => '2026-09-17T14:31:20.456Z',
            'exercise_id' => $exercise->id,
            'set_number' => 1,
            'weight' => '42.5',
            'reps' => '10',
        ],
        [
            'uuid' => 'action-finish',
            'type' => 'finish_session',
            'client_session_id' => 'client-session-uuid-iso',
            'occurred_at' => '2026-09-17T14:45:00.000Z',
        ],
    ];

    $this->actingAs($user)
        ->postJson(route('sync.workout-sessions'), ['actions' => $actions])
        ->assertOk();

    $session = WorkoutSession::where('workout_id', $workout->id)->where('user_id', $user->id)->firstOrFail();
    $setLog = $session->setLogs()->firstOrFail();

    expect((float) $setLog->weight)->toBe(42.5);
    expect($setLog->reps)->toBe(10);
    expect($setLog->client_uuid)->toBe('set-uuid-1');
});

test('resubmitting the exact same batch is idempotent and never duplicates the session or its sets', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();
    $workout = $user->workouts()->create(['name' => 'Treino Idempotente']);

    $actions = [
        [
            'uuid' => 'action-start',
            'type' => 'start_session',
            'client_session_id' => 'client-session-uuid-idem',
            'occurred_at' => now()->subMinutes(30)->toISOString(),
            'workout_id' => $workout->id,
        ],
        [
            'uuid' => 'action-set-1',
            'type' => 'log_set',
            'client_session_id' => 'client-session-uuid-idem',
            'client_set_id' => 'set-uuid-idem-1',
            'occurred_at' => now()->subMinutes(25)->toISOString(),
            'exercise_id' => $exercise->id,
            'set_number' => 1,
            'weight' => 40,
            'reps' => 10,
        ],
        [
            'uuid' => 'action-finish',
            'type' => 'finish_session',
            'client_session_id' => 'client-session-uuid-idem',
            'occurred_at' => now()->subMinutes(10)->toISOString(),
        ],
    ];

    // Simula um reenvio acidental de rede: o app nunca recebeu a confirmação
    // (200) da primeira tentativa e reenvia o lote inteiro assim que a
    // conexão volta.
    $this->actingAs($user)->postJson(route('sync.workout-sessions'), ['actions' => $actions])->assertOk();
    $this->actingAs($user)->postJson(route('sync.workout-sessions'), ['actions' => $actions])->assertOk();

    expect(WorkoutSession::where('workout_id', $workout->id)->where('user_id', $user->id)->count())->toBe(1);

    $session = WorkoutSession::where('workout_id', $workout->id)->where('user_id', $user->id)->firstOrFail();
    expect($session->setLogs()->count())->toBe(1);
    expect(SetLog::where('client_uuid', 'set-uuid-idem-1')->count())->toBe(1);
});

test('a batch cannot sync a workout session belonging to another user: the action is left out of results and nothing is persisted', function () {
    $this->seed(ExerciseSeeder::class);
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $exercise = Exercise::first();
    $workout = $owner->workouts()->create(['name' => 'Treino do Dono']);

    $actions = [
        [
            'uuid' => 'action-start',
            'type' => 'start_session',
            'client_session_id' => 'client-session-uuid-intruder',
            'occurred_at' => now()->subMinutes(5)->toISOString(),
            'workout_id' => $workout->id,
        ],
    ];

    $this->actingAs($intruder)
        ->postJson(route('sync.workout-sessions'), ['actions' => $actions])
        ->assertOk()
        ->assertExactJson(['results' => []]);

    expect(WorkoutSession::where('workout_id', $workout->id)->count())->toBe(0);
    expect(WorkoutSession::where('user_id', $intruder->id)->count())->toBe(0);
});

test('an owned session but a foreign orphan set inside the same batch is rejected without leaving a dangling session', function () {
    $this->seed(ExerciseSeeder::class);
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $exercise = Exercise::first();
    $ownerWorkout = $owner->workouts()->create(['name' => 'Treino do Dono']);

    $ownerSession = $owner->workoutSessions()->create([
        'workout_id' => $ownerWorkout->id,
        'started_at' => now()->subMinutes(20),
    ]);

    // O intruso tenta anexar uma série a uma sessão que não é dele,
    // referenciando-a diretamente por `session_id`.
    $actions = [
        [
            'uuid' => 'action-orphan-set',
            'type' => 'log_set',
            'client_session_id' => 'irrelevant-when-session-id-is-known',
            'session_id' => $ownerSession->id,
            'occurred_at' => now()->toISOString(),
            'exercise_id' => $exercise->id,
            'set_number' => 1,
            'weight' => 20,
            'reps' => 12,
        ],
    ];

    $this->actingAs($intruder)
        ->postJson(route('sync.workout-sessions'), ['actions' => $actions])
        ->assertOk()
        ->assertExactJson(['results' => []]);

    expect($ownerSession->refresh()->setLogs()->count())->toBe(0);
});
