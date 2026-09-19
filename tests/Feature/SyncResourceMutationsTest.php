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

    // A garantia de segurança continua: a mutação do intruso NUNCA é
    // aplicada. O que mudou é como isso chega ao dispositivo — desde que
    // ações são processadas isoladamente (ver `SyncService`, para que uma
    // única ação irresolúvel não trave o lote inteiro), a ação inválida
    // simplesmente não aparece em `results` (o servidor responde 200 com o
    // que pôde sincronizar) em vez de derrubar a requisição inteira com 422.
    $response = $this->actingAs($intruder)
        ->postJson(route('sync.mutations'), ['actions' => $actions])
        ->assertOk();

    expect($response->json('results'))->toBeEmpty();
    expect($program->fresh()->is_active)->toBeTrue();
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

// ─────────────────────────────────────────────────────────────────────────────
// Continuidade entre lotes de sincronização (mesmo bug de origem já corrigido
// para sessões em SyncTest.php): criar uma ficha/programa offline e, ANTES do
// próximo full sync trocar o id temporário pelo real, mexer nela de novo é o
// caminho normal de uso — não um caso extremo. Antes desta correção, se essas
// duas ações fossem enviadas em requisições SEPARADAS (o cenário comum quando
// a conexão está instável), a segunda travava para sempre com "Nenhum treino/
// programa correspondente foi encontrado", mesmo a entidade já existindo de
// verdade no banco.
// ─────────────────────────────────────────────────────────────────────────────

test('a later batch can update a workout created by an earlier, already-synced batch via tmp_ reference', function () {
    $user = User::factory()->create();
    $workoutUuid = 'workout-client-uuid-cross-batch';

    $this->actingAs($user)
        ->postJson(route('sync.mutations'), ['actions' => [[
            'uuid' => 'action-create',
            'type' => 'create_workout',
            'client_uuid' => $workoutUuid,
            'occurred_at' => now()->subMinutes(10)->toISOString(),
            'name' => 'Ficha Original',
            'days_of_week' => [],
            'exercises' => [],
        ]]])
        ->assertOk();

    $workout = Workout::where('client_uuid', $workoutUuid)->firstOrFail();

    // Segundo lote, requisição HTTP totalmente separada — como aconteceria
    // se o app sincronizasse assim que a ficha foi criada e só depois o
    // usuário a editasse, ainda antes do próximo full sync substituir o ID
    // temporário pelo real na cópia local.
    $this->actingAs($user)
        ->postJson(route('sync.mutations'), ['actions' => [[
            'uuid' => 'action-update',
            'type' => 'update_workout',
            'workout_id' => "tmp_{$workoutUuid}",
            'occurred_at' => now()->subMinutes(5)->toISOString(),
            'name' => 'Ficha Renomeada',
            'days_of_week' => [],
            'exercises' => [],
        ]]])
        ->assertOk()
        ->assertJsonPath('results.0.status', 'synced')
        ->assertJsonPath('results.0.id', $workout->id);

    expect($workout->refresh()->name)->toBe('Ficha Renomeada');
});

test('a later batch can delete a workout created by an earlier, already-synced batch via tmp_ reference', function () {
    $user = User::factory()->create();
    $workoutUuid = 'workout-client-uuid-cross-batch-delete';

    $this->actingAs($user)
        ->postJson(route('sync.mutations'), ['actions' => [[
            'uuid' => 'action-create',
            'type' => 'create_workout',
            'client_uuid' => $workoutUuid,
            'occurred_at' => now()->subMinutes(10)->toISOString(),
            'name' => 'Ficha Para Excluir',
            'days_of_week' => [],
            'exercises' => [],
        ]]])
        ->assertOk();

    $this->actingAs($user)
        ->postJson(route('sync.mutations'), ['actions' => [[
            'uuid' => 'action-delete',
            'type' => 'delete_workout',
            'workout_id' => "tmp_{$workoutUuid}",
            'occurred_at' => now()->subMinutes(5)->toISOString(),
        ]]])
        ->assertOk()
        ->assertJsonPath('results.0.status', 'synced');

    $this->assertDatabaseMissing('workouts', ['client_uuid' => $workoutUuid]);
});

test('a program created by an earlier batch can be resolved by a later attach_workout action', function () {
    $user = User::factory()->create();
    $programUuid = 'program-client-uuid-cross-batch';

    $this->actingAs($user)
        ->postJson(route('sync.mutations'), ['actions' => [[
            'uuid' => 'action-create-program',
            'type' => 'create_program',
            'client_uuid' => $programUuid,
            'occurred_at' => now()->subMinutes(10)->toISOString(),
            'name' => 'Programa Original',
        ]]])
        ->assertOk();

    $program = WorkoutProgram::where('client_uuid', $programUuid)->firstOrFail();
    $workout = $user->workouts()->create(['name' => 'Ficha Já Existente']);

    $this->actingAs($user)
        ->postJson(route('sync.mutations'), ['actions' => [[
            'uuid' => 'action-attach',
            'type' => 'attach_workout',
            'program_id' => "tmp_{$programUuid}",
            'workout_id' => $workout->id,
            'occurred_at' => now()->subMinutes(5)->toISOString(),
        ]]])
        ->assertOk()
        ->assertJsonPath('results.0.status', 'synced');

    expect($program->fresh()->workouts()->whereKey($workout->id)->exists())->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// Isolamento de falhas: uma ação permanentemente irresolúvel não pode mais
// bloquear as demais ações do mesmo lote — incluindo criações novas.
// ─────────────────────────────────────────────────────────────────────────────

test('one unresolvable action in a batch no longer blocks the other, valid actions in the same batch', function () {
    $user = User::factory()->create();

    $actions = [
        [
            'uuid' => 'action-broken-delete',
            'type' => 'delete_workout',
            // Referência a uma ficha que nunca existiu neste servidor — o
            // caso real de uma ação presa na fila local há muito tempo.
            'workout_id' => 'tmp_never-existed-uuid',
            'occurred_at' => now()->subMinutes(10)->toISOString(),
        ],
        [
            'uuid' => 'action-valid-create',
            'type' => 'create_program',
            'client_uuid' => 'client-program-uuid-survives',
            'occurred_at' => now()->subMinutes(5)->toISOString(),
            'name' => 'Programa Que Deve Sincronizar Mesmo Assim',
        ],
    ];

    $response = $this->actingAs($user)
        ->postJson(route('sync.mutations'), ['actions' => $actions])
        ->assertOk();

    // A ação quebrada simplesmente não aparece em `results` — o frontend só
    // remove da fila local (IndexedDB) os uuids presentes na resposta, então
    // ela permanece para uma tentativa futura, sem travar a outra.
    $resultUuids = collect($response->json('results'))->pluck('uuid')->all();
    expect($resultUuids)->not->toContain('action-broken-delete');
    expect($resultUuids)->toContain('action-valid-create');

    $this->assertDatabaseHas('workout_programs', [
        'user_id' => $user->id,
        'client_uuid' => 'client-program-uuid-survives',
    ]);
});
