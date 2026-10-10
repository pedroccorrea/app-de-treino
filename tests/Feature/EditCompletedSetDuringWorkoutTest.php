<?php

use App\Models\Exercise;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Services\WorkoutSessionService;
use Database\Seeders\ExerciseSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Phase 46 — Editar série já concluída durante o treino.
 *
 * A edição reaproveita o caminho de "Concluir série": a rota
 * `workout-sessions.sets.store` online e a ação `log_set` da fila offline.
 * Os dois aplicam `updateOrCreate` por `exercise_id` + `set_number`, então a
 * série é corrigida no lugar — nunca duplicada.
 */

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->exerciseA = Exercise::query()->global()->orderBy('id')->first();
    $this->exerciseB = Exercise::query()->global()->orderBy('id')->skip(1)->first();
    $this->user = User::factory()->create();
    $this->workout = $this->user->workouts()->create(['name' => 'Treino A']);
    $this->workout->exercises()->attach($this->exerciseA->id, ['order' => 1, 'target_sets' => 3, 'target_reps' => '10', 'rest_seconds' => 60]);
    $this->workout->exercises()->attach($this->exerciseB->id, ['order' => 2, 'target_sets' => 3, 'target_reps' => '10', 'rest_seconds' => 60]);
});

/** Treino em andamento com as séries informadas ([exercise, set_number, weight|null, reps]). */
function activeSessionWithSets(User $user, $workout, array $sets): WorkoutSession
{
    $session = $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now()->subMinutes(20),
        'completed_at' => null,
    ]);

    foreach ($sets as [$exercise, $setNumber, $weight, $reps]) {
        $session->setLogs()->create([
            'exercise_id' => $exercise->id,
            'set_number' => $setNumber,
            'weight' => $weight,
            'reps' => $reps,
        ]);
    }

    return $session;
}

function logSetFor(WorkoutSession $session, Exercise $exercise, int $setNumber): ?SetLog
{
    return $session->setLogs()->where('exercise_id', $exercise->id)->where('set_number', $setNumber)->first();
}

// ─── Online ───────────────────────────────────────────────────────────────────

test('reenviar uma série concluída a corrige no lugar, sem duplicar nem tocar nas outras', function () {
    $session = activeSessionWithSets($this->user, $this->workout, [
        [$this->exerciseA, 1, 40, 10],
        [$this->exerciseA, 2, 40, 8],
        [$this->exerciseB, 1, 20, 12],
    ]);

    $this->actingAs($this->user)
        ->from(route('workout-sessions.show', $session))
        ->post(route('workout-sessions.sets.store', $session), [
            'exercise_id' => $this->exerciseA->id,
            'set_number' => 1,
            'weight' => 50,
            'reps' => 12,
        ])
        ->assertRedirect(route('workout-sessions.show', $session))
        ->assertSessionHasNoErrors();

    expect($session->setLogs()->count())->toBe(3)
        ->and($session->setLogs()->where('exercise_id', $this->exerciseA->id)->where('set_number', 1)->count())->toBe(1);

    $edited = logSetFor($session, $this->exerciseA, 1);
    expect((float) $edited->weight)->toBe(50.0)->and($edited->reps)->toBe(12);

    // As outras séries e o próprio treino em andamento seguem iguais.
    expect((float) logSetFor($session, $this->exerciseA, 2)->weight)->toBe(40.0)
        ->and(logSetFor($session, $this->exerciseA, 2)->reps)->toBe(8)
        ->and((float) logSetFor($session, $this->exerciseB, 1)->weight)->toBe(20.0)
        ->and($session->refresh()->completed_at)->toBeNull();

    // A tela do treino recebe a versão corrigida (base do volume local).
    $this->get(route('workout-sessions.show', $session))
        ->assertInertia(fn (Assert $page) => $page
            ->component('WorkoutSessions/Active')
            ->has('setLogs', 3)
            ->where('setLogs', fn ($logs) => collect($logs)->contains(
                fn ($log) => $log['exercise_id'] === $this->exerciseA->id
                    && $log['set_number'] === 1
                    && (float) $log['weight'] === 50.0
                    && $log['reps'] === 12
            ))
        );
});

test('a série corrigida se reflete no volume do histórico e em "Última" do próximo treino', function () {
    $session = activeSessionWithSets($this->user, $this->workout, [
        [$this->exerciseA, 1, 40, 10],
        [$this->exerciseA, 2, 40, 8],
    ]);

    $this->actingAs($this->user)
        ->post(route('workout-sessions.sets.store', $session), [
            'exercise_id' => $this->exerciseA->id,
            'set_number' => 1,
            'weight' => 50,
            'reps' => 12,
        ])
        ->assertSessionHasNoErrors();

    $this->post(route('workout-sessions.finish', $session))->assertRedirect(route('dashboard'));

    $this->get(route('history.show', $session))
        ->assertInertia(fn (Assert $page) => $page
            ->where('session.volume', 50 * 12 + 40 * 8)
            ->where('session.exercises.0.sets.0.weight', 50)
            ->where('session.exercises.0.sets.0.reps', 12)
        );

    $lastLog = app(WorkoutSessionService::class)
        ->getLastLogsForExercises($this->user, [$this->exerciseA->id])[$this->exerciseA->id];

    expect($lastLog['weight'])->toBe(50.0)
        ->and($lastLog['reps'])->toBe(12)
        ->and($lastLog['sets'])->toHaveCount(2);
});

test('uma série concluída pode ser corrigida para ficar sem carga, e uma sem carga pode ganhar carga', function () {
    $session = activeSessionWithSets($this->user, $this->workout, [
        [$this->exerciseA, 1, 40, 10],
        [$this->exerciseA, 2, null, 15],
    ]);

    $this->actingAs($this->user)
        ->post(route('workout-sessions.sets.store', $session), [
            'exercise_id' => $this->exerciseA->id, 'set_number' => 1, 'weight' => null, 'reps' => 10,
        ])
        ->assertSessionHasNoErrors();

    $this->post(route('workout-sessions.sets.store', $session), [
        'exercise_id' => $this->exerciseA->id, 'set_number' => 2, 'weight' => 12.5, 'reps' => 15,
    ])->assertSessionHasNoErrors();

    expect($session->setLogs()->count())->toBe(2)
        ->and(logSetFor($session, $this->exerciseA, 1)->weight)->toBeNull()
        ->and((float) logSetFor($session, $this->exerciseA, 2)->weight)->toBe(12.5);
});

test('a correção usa os mesmos limites do registro de série e não altera nada se inválida', function () {
    $session = activeSessionWithSets($this->user, $this->workout, [[$this->exerciseA, 1, 40, 10]]);

    $this->actingAs($this->user)
        ->post(route('workout-sessions.sets.store', $session), [
            'exercise_id' => $this->exerciseA->id, 'set_number' => 1, 'weight' => 40, 'reps' => 0,
        ])
        ->assertSessionHasErrors('reps');

    $set = logSetFor($session, $this->exerciseA, 1);
    expect($set->reps)->toBe(10)->and($session->setLogs()->count())->toBe(1);
});

// ─── Offline (fila de sincronização) ──────────────────────────────────────────

test('corrigir offline uma série feita online atualiza a mesma linha ao sincronizar', function () {
    $session = activeSessionWithSets($this->user, $this->workout, [
        [$this->exerciseA, 1, 40, 10],
        [$this->exerciseA, 2, 40, 8],
    ]);

    // Mesma forma que `useCompletedSetEdit` enfileira: `client_set_id` novo,
    // mas o mesmo exercício + número de série.
    $actions = [[
        'uuid' => 'edit-offline-1',
        'type' => 'log_set',
        'client_session_id' => "session-{$session->id}",
        'client_set_id' => 'edit-set-uuid-1',
        'session_id' => $session->id,
        'occurred_at' => now()->toISOString(),
        'exercise_id' => $this->exerciseA->id,
        'set_number' => 1,
        'weight' => 45,
        'reps' => 11,
    ]];

    $this->actingAs($this->user)
        ->postJson(route('sync.workout-sessions'), ['actions' => $actions])
        ->assertOk();

    // Reenviar o mesmo lote (reconexão instável) também não duplica.
    $this->postJson(route('sync.workout-sessions'), ['actions' => $actions])->assertOk();

    expect($session->setLogs()->count())->toBe(2)
        ->and((float) logSetFor($session, $this->exerciseA, 1)->weight)->toBe(45.0)
        ->and(logSetFor($session, $this->exerciseA, 1)->reps)->toBe(11)
        ->and((float) logSetFor($session, $this->exerciseA, 2)->weight)->toBe(40.0);
});

test('um treino feito offline com uma série corrigida sincroniza só a versão final dela', function () {
    $actions = [
        [
            'uuid' => 'start',
            'type' => 'start_session',
            'client_session_id' => 'offline-session-46',
            'occurred_at' => now()->subMinutes(30)->toISOString(),
            'workout_id' => $this->workout->id,
        ],
        [
            'uuid' => 'set-1',
            'type' => 'log_set',
            'client_session_id' => 'offline-session-46',
            'client_set_id' => 'set-uuid-1',
            'occurred_at' => now()->subMinutes(25)->toISOString(),
            'exercise_id' => $this->exerciseA->id,
            'set_number' => 1,
            'weight' => 40,
            'reps' => 10,
        ],
        [
            'uuid' => 'set-2',
            'type' => 'log_set',
            'client_session_id' => 'offline-session-46',
            'client_set_id' => 'set-uuid-2',
            'occurred_at' => now()->subMinutes(22)->toISOString(),
            'exercise_id' => $this->exerciseA->id,
            'set_number' => 2,
            'weight' => 40,
            'reps' => 9,
        ],
        // Correção da série 1, feita depois da série 2.
        [
            'uuid' => 'edit-set-1',
            'type' => 'log_set',
            'client_session_id' => 'offline-session-46',
            'client_set_id' => 'set-uuid-1-edit',
            'occurred_at' => now()->subMinutes(20)->toISOString(),
            'exercise_id' => $this->exerciseA->id,
            'set_number' => 1,
            'weight' => null,
            'reps' => 14,
        ],
        [
            'uuid' => 'finish',
            'type' => 'finish_session',
            'client_session_id' => 'offline-session-46',
            'occurred_at' => now()->subMinutes(10)->toISOString(),
        ],
    ];

    $this->actingAs($this->user)
        ->postJson(route('sync.workout-sessions'), ['actions' => $actions])
        ->assertOk();
    $this->postJson(route('sync.workout-sessions'), ['actions' => $actions])->assertOk();

    $session = WorkoutSession::where('user_id', $this->user->id)->where('client_uuid', 'offline-session-46')->sole();

    expect($session->setLogs()->count())->toBe(2)
        ->and(logSetFor($session, $this->exerciseA, 1)->weight)->toBeNull()
        ->and(logSetFor($session, $this->exerciseA, 1)->reps)->toBe(14)
        ->and(logSetFor($session, $this->exerciseA, 2)->reps)->toBe(9);
});

// ─── Autorização ──────────────────────────────────────────────────────────────

test('ninguém corrige a série do treino em andamento de outro usuário, online ou pela sincronização', function () {
    $session = activeSessionWithSets($this->user, $this->workout, [[$this->exerciseA, 1, 40, 10]]);
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->post(route('workout-sessions.sets.store', $session), [
            'exercise_id' => $this->exerciseA->id, 'set_number' => 1, 'weight' => 999, 'reps' => 1,
        ])
        ->assertForbidden();

    $this->postJson(route('sync.workout-sessions'), ['actions' => [[
        'uuid' => 'intruder-edit',
        'type' => 'log_set',
        'client_session_id' => "session-{$session->id}",
        'client_set_id' => 'intruder-set',
        'session_id' => $session->id,
        'occurred_at' => now()->toISOString(),
        'exercise_id' => $this->exerciseA->id,
        'set_number' => 1,
        'weight' => 999,
        'reps' => 1,
    ]]]);

    $set = logSetFor($session, $this->exerciseA, 1);
    expect($session->setLogs()->count())->toBe(1)
        ->and((float) $set->weight)->toBe(40.0)
        ->and($set->reps)->toBe(10)
        ->and(SetLog::where('client_uuid', 'intruder-set')->exists())->toBeFalse();
});

// ─── Frontend: contrato da tela ───────────────────────────────────────────────

test('a tela do treino reaproveita o editor da Phase 45 para corrigir séries concluídas', function () {
    $active = file_get_contents(resource_path('js/Pages/WorkoutSessions/Active.vue'));
    $card = file_get_contents(resource_path('js/Components/WorkoutSessions/ExerciseActiveCard.vue'));
    $composable = file_get_contents(resource_path('js/Composables/useCompletedSetEdit.js'));
    $modal = file_get_contents(resource_path('js/Components/Sets/EditSetModal.vue'));

    expect($active)->toContain("import EditSetModal from '@/Components/Sets/EditSetModal.vue'")
        ->and($active)->toContain('@edit-set=')
        ->and($card)->toContain("\$emit('edit-set', set.number)")
        ->and($modal)->toContain('<SetEditor')
        // Mesmo caminho de "Concluir série": nada de rota nova.
        ->and($composable)->toContain("route('workout-sessions.sets.store'")
        ->and($composable)->toContain("type: 'log_set'")
        // Isolamento: a edição nunca inicia descanso nem toca alarme.
        ->and($composable)->not->toContain('startRest')
        ->and($composable)->not->toContain('playRestAlarm');
});
