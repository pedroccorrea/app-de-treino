<?php

use App\Enums\MuscleGroup;
use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutSession;
use Database\Seeders\ExerciseSeeder;

/**
 * Contrato de isolamento por ação do endpoint `sync.workout-sessions`: uma
 * referência irresolúvel (ficha excluída, de outro usuário, ainda não
 * sincronizada) fica de fora de `results` sem derrubar o lote; o 422 é
 * reservado para payloads estruturalmente inválidos.
 */
test('a batch with one invalid workout_id processes the valid actions and omits only the invalid one from results', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();
    $workout = $user->workouts()->create(['name' => 'Treino A']);

    $actions = [
        [
            'uuid' => 'action-start',
            'type' => 'start_session',
            'client_session_id' => 'client-session-valid',
            'occurred_at' => now()->subMinutes(10)->toISOString(),
            'workout_id' => $workout->id,
        ],
        [
            'uuid' => 'action-stale-start',
            'type' => 'start_session',
            'client_session_id' => 'client-session-stale',
            'occurred_at' => now()->subMinutes(9)->toISOString(),
            'workout_id' => 999999,
        ],
        [
            'uuid' => 'action-set',
            'type' => 'log_set',
            'client_session_id' => 'client-session-valid',
            'occurred_at' => now()->subMinutes(8)->toISOString(),
            'exercise_id' => $exercise->id,
            'set_number' => 1,
            'weight' => 40,
            'reps' => 10,
        ],
    ];

    $response = $this->actingAs($user)
        ->postJson(route('sync.workout-sessions'), ['actions' => $actions])
        ->assertOk();

    expect(collect($response->json('results'))->pluck('uuid')->all())->toBe(['action-start', 'action-set']);

    $session = WorkoutSession::where('client_uuid', 'client-session-valid')->firstOrFail();
    expect($session->setLogs()->count())->toBe(1);
    expect(WorkoutSession::where('client_uuid', 'client-session-stale')->exists())->toBeFalse();
});

test('a session started online and finished offline is closed on the server by a later batch, without a 422', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();
    $workout = $user->workouts()->create(['name' => 'Agora vai']);
    $workout->exercises()->attach($exercise->id, ['order' => 1, 'target_sets' => 3, 'target_reps' => '10']);

    // Início ONLINE, pela rota real — é o que cria a sessão 66 do relato.
    $this->actingAs($user)->post(route('workouts.start', $workout));
    $session = WorkoutSession::where('user_id', $user->id)->whereNull('completed_at')->firstOrFail();

    // Lote enviado ao reconectar: as séries e o `finish_session` feitos
    // offline, misturados com dois `start_session` velhos presos na fila
    // (fichas já excluídas) exatamente nas posições 3 e 6 do relato.
    $clientSessionId = "session-{$session->id}";
    $base = now()->subMinutes(30);
    $set = fn (string $uuid, int $number, int $minute) => [
        'uuid' => $uuid,
        'type' => 'log_set',
        'client_session_id' => $clientSessionId,
        'session_id' => $session->id,
        'occurred_at' => $base->copy()->addMinutes($minute)->toISOString(),
        'exercise_id' => $exercise->id,
        'set_number' => $number,
        'weight' => '50',
        'reps' => '8',
    ];
    $staleStart = fn (string $uuid, int $minute) => [
        'uuid' => $uuid,
        'type' => 'start_session',
        'client_session_id' => "stale-{$uuid}",
        'occurred_at' => $base->copy()->subDays(3)->addMinutes($minute)->toISOString(),
        'workout_id' => 1_000 + $minute,
    ];

    $actions = [
        $set('set-1', 1, 1),
        $set('set-2', 2, 3),
        $set('set-3', 3, 5),
        $staleStart('stale-a', 1),
        $set('set-4', 4, 7),
        $set('set-5', 5, 9),
        $staleStart('stale-b', 2),
        [
            'uuid' => 'finish',
            'type' => 'finish_session',
            'client_session_id' => $clientSessionId,
            'session_id' => $session->id,
            'occurred_at' => $base->copy()->addMinutes(12)->toISOString(),
        ],
    ];

    $response = $this->actingAs($user)
        ->postJson(route('sync.workout-sessions'), ['actions' => $actions])
        ->assertOk();

    expect(collect($response->json('results'))->pluck('uuid')->sort()->values()->all())
        ->toBe(['finish', 'set-1', 'set-2', 'set-3', 'set-4', 'set-5']);

    $session->refresh();
    expect($session->completed_at)->not->toBeNull();
    expect($session->setLogs()->count())->toBe(5);
    expect(WorkoutSession::where('user_id', $user->id)->whereNull('completed_at')->exists())->toBeFalse();
});

test('a workout_id owned by another user is still rejected (left out of results) without affecting the rest of the batch', function () {
    $this->seed(ExerciseSeeder::class);
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $exercise = Exercise::first();
    $foreignWorkout = $owner->workouts()->create(['name' => 'Treino Alheio']);
    $ownWorkout = $intruder->workouts()->create(['name' => 'Treino Próprio']);

    $actions = [
        [
            'uuid' => 'action-foreign-start',
            'type' => 'start_session',
            'client_session_id' => 'client-session-foreign',
            'occurred_at' => now()->subMinutes(10)->toISOString(),
            'workout_id' => $foreignWorkout->id,
        ],
        [
            'uuid' => 'action-foreign-set',
            'type' => 'log_set',
            'client_session_id' => 'client-session-foreign',
            'occurred_at' => now()->subMinutes(9)->toISOString(),
            'exercise_id' => $exercise->id,
            'set_number' => 1,
            'reps' => 10,
        ],
        [
            'uuid' => 'action-own-start',
            'type' => 'start_session',
            'client_session_id' => 'client-session-own',
            'occurred_at' => now()->subMinutes(8)->toISOString(),
            'workout_id' => $ownWorkout->id,
        ],
        [
            'uuid' => 'action-own-finish',
            'type' => 'finish_session',
            'client_session_id' => 'client-session-own',
            'occurred_at' => now()->subMinutes(2)->toISOString(),
        ],
    ];

    $response = $this->actingAs($intruder)
        ->postJson(route('sync.workout-sessions'), ['actions' => $actions])
        ->assertOk();

    expect(collect($response->json('results'))->pluck('uuid')->all())->toBe(['action-own-start', 'action-own-finish']);

    expect(WorkoutSession::where('workout_id', $foreignWorkout->id)->exists())->toBeFalse();
    expect(WorkoutSession::where('client_uuid', 'client-session-own')->firstOrFail()->completed_at)->not->toBeNull();
});

test('a custom exercise owned by another user is left out of results instead of being logged', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $foreignExercise = Exercise::create([
        'user_id' => $owner->id,
        'name' => 'Exercício Alheio',
        'primary_muscle_group' => MuscleGroup::Chest,
    ]);
    $workout = $intruder->workouts()->create(['name' => 'Treino']);

    $actions = [
        [
            'uuid' => 'action-start',
            'type' => 'start_session',
            'client_session_id' => 'client-session-x',
            'occurred_at' => now()->subMinutes(5)->toISOString(),
            'workout_id' => $workout->id,
        ],
        [
            'uuid' => 'action-foreign-exercise',
            'type' => 'log_set',
            'client_session_id' => 'client-session-x',
            'occurred_at' => now()->subMinutes(4)->toISOString(),
            'exercise_id' => $foreignExercise->id,
            'set_number' => 1,
            'reps' => 10,
        ],
    ];

    $response = $this->actingAs($intruder)
        ->postJson(route('sync.workout-sessions'), ['actions' => $actions])
        ->assertOk();

    expect(collect($response->json('results'))->pluck('uuid')->all())->toBe(['action-start']);
    expect(WorkoutSession::where('client_uuid', 'client-session-x')->firstOrFail()->setLogs()->count())->toBe(0);
});

test('a structurally invalid payload is still rejected with 422', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('sync.workout-sessions'), ['actions' => [
            [
                'uuid' => 'action-start',
                'type' => 'start_session',
                'client_session_id' => 'client-session-y',
                'occurred_at' => now()->toISOString(),
                'workout_id' => ['not-an-id'],
            ],
            [
                'uuid' => 'action-set',
                'type' => 'log_set',
                'client_session_id' => 'client-session-y',
                'occurred_at' => now()->toISOString(),
            ],
        ]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['actions.0.workout_id', 'actions.1.exercise_id', 'actions.1.reps']);
});
