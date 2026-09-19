<?php

use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutSession;
use Database\Seeders\ExerciseSeeder;

test('guests cannot sync offline workout sessions', function () {
    $this->postJson(route('sync.workout-sessions'), ['actions' => []])
        ->assertUnauthorized();
});

test('it processes a fully offline session (start, sets and finish) in chronological order regardless of submission order', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();
    $workout = $user->workouts()->create(['name' => 'Treino Offline']);

    $clientSessionId = 'client-session-uuid-1';
    $startedAt = now()->subMinutes(40);
    $set1At = now()->subMinutes(38);
    $set2At = now()->subMinutes(35);
    $finishedAt = now()->subMinutes(20);

    // Deliberately submitted out of chronological order (finish first, then
    // set 2, then set 1, then start) to prove the service reorders by
    // `occurred_at` rather than trusting array/network arrival order.
    $actions = [
        [
            'uuid' => 'action-finish',
            'type' => 'finish_session',
            'client_session_id' => $clientSessionId,
            'occurred_at' => $finishedAt->toISOString(),
        ],
        [
            'uuid' => 'action-set-2',
            'type' => 'log_set',
            'client_session_id' => $clientSessionId,
            'occurred_at' => $set2At->toISOString(),
            'exercise_id' => $exercise->id,
            'set_number' => 2,
            'weight' => 42.5,
            'reps' => 8,
        ],
        [
            'uuid' => 'action-set-1',
            'type' => 'log_set',
            'client_session_id' => $clientSessionId,
            'occurred_at' => $set1At->toISOString(),
            'exercise_id' => $exercise->id,
            'set_number' => 1,
            'weight' => 40,
            'reps' => 10,
        ],
        [
            'uuid' => 'action-start',
            'type' => 'start_session',
            'client_session_id' => $clientSessionId,
            'occurred_at' => $startedAt->toISOString(),
            'workout_id' => $workout->id,
        ],
    ];

    $response = $this->actingAs($user)
        ->postJson(route('sync.workout-sessions'), ['actions' => $actions])
        ->assertOk();

    $session = WorkoutSession::where('workout_id', $workout->id)->where('user_id', $user->id)->firstOrFail();

    // `started_at`/`completed_at` are `timestamp` columns (second precision),
    // so we compare formatted values rather than exact (microsecond) equality.
    expect($session->started_at->toDateTimeString())->toBe($startedAt->toDateTimeString());
    expect($session->completed_at->toDateTimeString())->toBe($finishedAt->toDateTimeString());

    $this->assertDatabaseHas('set_logs', [
        'workout_session_id' => $session->id,
        'exercise_id' => $exercise->id,
        'set_number' => 1,
        'weight' => 40,
        'reps' => 10,
    ]);
    $this->assertDatabaseHas('set_logs', [
        'workout_session_id' => $session->id,
        'exercise_id' => $exercise->id,
        'set_number' => 2,
        'weight' => 42.5,
        'reps' => 8,
    ]);

    $setLog1 = $session->setLogs()->where('set_number', 1)->firstOrFail();
    $setLog2 = $session->setLogs()->where('set_number', 2)->firstOrFail();

    // Persisted timestamps must reflect the real (offline) chronology, not
    // the order the actions happened to arrive in the request payload.
    expect($setLog1->created_at->toDateTimeString())->toBe($set1At->toDateTimeString());
    expect($setLog2->created_at->toDateTimeString())->toBe($set2At->toDateTimeString());
    expect($setLog1->created_at->lessThan($setLog2->created_at))->toBeTrue();
    expect($session->started_at->lessThan($setLog1->created_at))->toBeTrue();
    expect($setLog2->created_at->lessThan($session->completed_at))->toBeTrue();

    // The response also lists results in chronological order, matching the
    // order the actions were actually applied (start, set 1, set 2, finish),
    // not the scrambled order they were submitted in.
    $response->assertExactJson([
        'results' => [
            ['uuid' => 'action-start', 'client_session_id' => $clientSessionId, 'session_id' => $session->id, 'status' => 'synced'],
            ['uuid' => 'action-set-1', 'client_session_id' => $clientSessionId, 'session_id' => $session->id, 'status' => 'synced'],
            ['uuid' => 'action-set-2', 'client_session_id' => $clientSessionId, 'session_id' => $session->id, 'status' => 'synced'],
            ['uuid' => 'action-finish', 'client_session_id' => $clientSessionId, 'session_id' => $session->id, 'status' => 'synced'],
        ],
    ]);
});

test('it appends offline sets to a session that was already started online', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();
    $workout = $user->workouts()->create(['name' => 'Treino Misto']);

    $session = $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now()->subMinutes(30),
    ]);

    $occurredAt = now()->subMinutes(10);

    $actions = [
        [
            'uuid' => 'action-offline-set',
            'type' => 'log_set',
            'client_session_id' => 'irrelevant-when-session-id-is-known',
            'session_id' => $session->id,
            'occurred_at' => $occurredAt->toISOString(),
            'exercise_id' => $exercise->id,
            'set_number' => 1,
            'weight' => 60,
            'reps' => 5,
        ],
    ];

    $this->actingAs($user)
        ->postJson(route('sync.workout-sessions'), ['actions' => $actions])
        ->assertOk()
        ->assertJson(['results' => [['uuid' => 'action-offline-set', 'session_id' => $session->id, 'status' => 'synced']]]);

    $this->assertDatabaseHas('set_logs', [
        'workout_session_id' => $session->id,
        'exercise_id' => $exercise->id,
        'set_number' => 1,
        'weight' => 60,
        'reps' => 5,
    ]);
});

test('a user cannot sync actions against another users workout or session', function () {
    $this->seed(ExerciseSeeder::class);
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $workout = $owner->workouts()->create(['name' => 'Treino Alheio']);

    $actions = [
        [
            'uuid' => 'action-start',
            'type' => 'start_session',
            'client_session_id' => 'client-session-uuid-x',
            'occurred_at' => now()->toISOString(),
            'workout_id' => $workout->id,
        ],
    ];

    $this->actingAs($intruder)
        ->postJson(route('sync.workout-sessions'), ['actions' => $actions])
        ->assertStatus(422);
});

test('starting an offline session closes any ghost session left open for the same user', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $workout = $user->workouts()->create(['name' => 'Treino Offline']);

    // Sessão fantasma que ficou aberta por um bug/teste anterior — o mesmo
    // cenário que WorkoutSessionService::startSession já resolve online.
    $ghostSession = $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now()->subDays(3),
    ]);

    $actions = [
        [
            'uuid' => 'action-start',
            'type' => 'start_session',
            'client_session_id' => 'client-session-uuid-ghost',
            'occurred_at' => now()->toISOString(),
            'workout_id' => $workout->id,
        ],
    ];

    $this->actingAs($user)
        ->postJson(route('sync.workout-sessions'), ['actions' => $actions])
        ->assertOk();

    expect($ghostSession->refresh()->completed_at)->not->toBeNull();

    $newSession = WorkoutSession::where('workout_id', $workout->id)->where('id', '!=', $ghostSession->id)->firstOrFail();
    expect($newSession->completed_at)->toBeNull();
});

test('resending the same batch is idempotent and never duplicates the workout session', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();
    $workout = $user->workouts()->create(['name' => 'Treino Offline']);

    $clientSessionId = 'client-session-uuid-retry';
    $actions = [
        [
            'uuid' => 'action-start',
            'type' => 'start_session',
            'client_session_id' => $clientSessionId,
            'occurred_at' => now()->subMinutes(30)->toISOString(),
            'workout_id' => $workout->id,
        ],
        [
            'uuid' => 'action-set-1',
            'type' => 'log_set',
            'client_session_id' => $clientSessionId,
            'occurred_at' => now()->subMinutes(25)->toISOString(),
            'exercise_id' => $exercise->id,
            'set_number' => 1,
            'weight' => 40,
            'reps' => 10,
        ],
        [
            'uuid' => 'action-finish',
            'type' => 'finish_session',
            'client_session_id' => $clientSessionId,
            'occurred_at' => now()->subMinutes(10)->toISOString(),
        ],
    ];

    // Simula uma reconexão instável: o app nunca recebeu a confirmação da
    // primeira tentativa (ou o usuário reabriu o app antes de esvaziar a
    // fila) e reenvia o lote inteiro pela segunda vez.
    $this->actingAs($user)->postJson(route('sync.workout-sessions'), ['actions' => $actions])->assertOk();
    $this->actingAs($user)->postJson(route('sync.workout-sessions'), ['actions' => $actions])->assertOk();

    $sessions = WorkoutSession::where('workout_id', $workout->id)->where('user_id', $user->id)->get();
    expect($sessions)->toHaveCount(1);

    $session = $sessions->first();
    expect($session->completed_at)->not->toBeNull();
    expect($session->setLogs()->count())->toBe(1);
});

test('logging a set without a resolvable session stays queued for retry instead of failing the whole batch', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();

    $actions = [
        [
            'uuid' => 'action-orphan-set',
            'type' => 'log_set',
            'client_session_id' => 'never-started',
            'occurred_at' => now()->toISOString(),
            'exercise_id' => $exercise->id,
            'set_number' => 1,
            'reps' => 10,
        ],
    ];

    // Ações são processadas isoladamente (ver `SyncService`): uma que não
    // pode ser resolvida agora não derruba a requisição com 422 — ela
    // simplesmente fica de fora de `results`, e o dispositivo a mantém na
    // fila local para tentar de novo (ex.: um lote futuro que finalmente
    // traga o `start_session` correspondente).
    $response = $this->actingAs($user)
        ->postJson(route('sync.workout-sessions'), ['actions' => $actions])
        ->assertOk();

    expect($response->json('results'))->toBeEmpty();
});

// ─────────────────────────────────────────────────────────────────────────────
// Phase 38 — Continuidade de sessão entre lotes de sincronização
//
// A conexão voltar no meio do treino é o caso normal, não a exceção: a fila é
// enviada, confirmada e esvaziada, e o usuário continua registrando séries. O
// lote seguinte chega SEM nenhum `start_session` dentro — e antes desta fase
// ele era rejeitado com 422 ("Nenhuma sessão de treino correspondente…"),
// deixando as séries presas na fila local para sempre.
// ─────────────────────────────────────────────────────────────────────────────

test('a later batch without start_session still finds the session created offline, by client_uuid', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();
    $workout = $user->workouts()->create(['name' => 'Treino Offline']);

    $clientSessionId = 'client-session-uuid-split-batch';

    // Primeiro lote: a sessão nasce offline e é sincronizada.
    $this->actingAs($user)
        ->postJson(route('sync.workout-sessions'), ['actions' => [[
            'uuid' => 'action-start',
            'type' => 'start_session',
            'client_session_id' => $clientSessionId,
            'occurred_at' => now()->subMinutes(30)->toISOString(),
            'workout_id' => $workout->id,
        ]]])
        ->assertOk();

    $session = WorkoutSession::where('client_uuid', $clientSessionId)->firstOrFail();

    // Segundo lote: só séries, sem `session_id` (o dispositivo nunca soube o
    // ID real) e sem `start_session` (já foi confirmado e removido da fila).
    $this->actingAs($user)
        ->postJson(route('sync.workout-sessions'), ['actions' => [
            [
                'uuid' => 'action-set-late',
                'type' => 'log_set',
                'client_session_id' => $clientSessionId,
                'occurred_at' => now()->subMinutes(10)->toISOString(),
                'exercise_id' => $exercise->id,
                'set_number' => 1,
                'reps' => 10,
                'weight' => 60,
            ],
            [
                'uuid' => 'action-finish-late',
                'type' => 'finish_session',
                'client_session_id' => $clientSessionId,
                'occurred_at' => now()->subMinutes(5)->toISOString(),
            ],
        ]])
        ->assertOk()
        ->assertJsonPath('results.0.session_id', $session->id);

    expect($session->refresh()->setLogs()->count())->toBe(1);
    expect($session->completed_at)->not->toBeNull();
    expect(WorkoutSession::where('user_id', $user->id)->count())->toBe(1);
});

test('a batch of sets for a session started online resolves by the session-<id> label alone', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();
    $workout = $user->workouts()->create(['name' => 'Treino Online']);

    $session = $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now()->subMinutes(20),
    ]);

    $this->actingAs($user)
        ->postJson(route('sync.workout-sessions'), ['actions' => [[
            'uuid' => 'action-set-label-only',
            'type' => 'log_set',
            // Rótulo que `WorkoutSessions/Active.vue` gera quando a sessão
            // nasceu online — aqui sem o `session_id` junto, simulando um
            // payload antigo já parado na fila do aparelho.
            'client_session_id' => "session-{$session->id}",
            'occurred_at' => now()->subMinutes(5)->toISOString(),
            'exercise_id' => $exercise->id,
            'set_number' => 1,
            'reps' => 12,
            'weight' => 40,
        ]]])
        ->assertOk()
        ->assertJsonPath('results.0.session_id', $session->id);

    expect($session->refresh()->setLogs()->count())->toBe(1);
});

test('a session started offline on a workout also created offline syncs via its tmp_ reference', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();

    $workoutUuid = 'workout-client-uuid-38';

    // Ficha criada offline: é o lote de mutações de recurso que a cria de
    // verdade (e o frontend sempre envia esse grupo primeiro — ver
    // `flushQueue` em `useOfflineSync.js`).
    $this->actingAs($user)
        ->postJson(route('sync.mutations'), ['actions' => [[
            'uuid' => 'action-create-workout',
            'type' => 'create_workout',
            'client_uuid' => $workoutUuid,
            'occurred_at' => now()->subMinutes(50)->toISOString(),
            'name' => 'Ficha Nascida Offline',
            'days_of_week' => [],
            'exercises' => [],
        ]]])
        ->assertOk();

    $this->actingAs($user)
        ->postJson(route('sync.workout-sessions'), ['actions' => [[
            'uuid' => 'action-start-tmp',
            'type' => 'start_session',
            'client_session_id' => 'client-session-uuid-tmp-workout',
            'occurred_at' => now()->subMinutes(40)->toISOString(),
            'workout_id' => "tmp_{$workoutUuid}",
        ]]])
        ->assertOk();

    $workout = $user->workouts()->where('client_uuid', $workoutUuid)->firstOrFail();

    expect(WorkoutSession::where('client_uuid', 'client-session-uuid-tmp-workout')->firstOrFail()->workout_id)
        ->toBe($workout->id);
});
