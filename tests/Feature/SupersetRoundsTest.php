<?php

use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSession;
use Database\Seeders\ExerciseSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/*
 * Phase 53: rodada do bi-set/tri-set no treino em andamento.
 *
 * A lógica de rodadas (`resources/js/Utils/supersetRounds.js`) é verificada
 * pelo Node via `tests/js/supersetRounds.check.mjs`. No backend nada mudou:
 * "Concluir rodada" envia, em sequência, uma série normal por exercício da
 * rodada — pela rota `workout-sessions.sets.store` online ou como `log_set`
 * na fila offline — e o `updateOrCreate` por exercício + série garante que
 * reenviar nunca duplica.
 */

function srJsOutput(): array
{
    $node = (new ExecutableFinder)->find('node');

    if ($node === null) {
        test()->markTestSkipped('Node.js não encontrado para verificar a lógica JS das rodadas.');
    }

    $process = new Process([$node, base_path('tests/js/supersetRounds.check.mjs')], base_path());
    $process->mustRun();

    return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * Ficha com um bi-set (A+B) e um exercício sozinho (C).
 */
function srBiSetWorkout(User $user, array $exerciseIds): Workout
{
    [$a, $b, $c] = $exerciseIds;

    $workout = $user->workouts()->create(['name' => 'Treino Bi-set']);
    $workout->exercises()->attach($a, ['order' => 0, 'target_sets' => 3, 'target_reps' => '10', 'rest_seconds' => 60, 'superset_group' => 1]);
    $workout->exercises()->attach($b, ['order' => 1, 'target_sets' => 3, 'target_reps' => '10', 'rest_seconds' => 90, 'superset_group' => 1]);
    $workout->exercises()->attach($c, ['order' => 2, 'target_sets' => 3, 'target_reps' => '10', 'rest_seconds' => 60]);

    return $workout;
}

function srActiveSession(User $user, Workout $workout): WorkoutSession
{
    return $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now()->subMinutes(10),
        'completed_at' => null,
    ]);
}

/**
 * Ação `log_set` exatamente como `completeRound()` enfileira: cada série da
 * rodada com o seu próprio `client_set_id`.
 */
function srLogSetAction(WorkoutSession $session, int $exerciseId, int $setNumber, float $weight, int $reps, string $id): array
{
    return [
        'uuid' => "action-{$id}",
        'type' => 'log_set',
        'client_session_id' => "session-{$session->id}",
        'client_set_id' => "set-{$id}",
        'session_id' => $session->id,
        'occurred_at' => now()->toISOString(),
        'exercise_id' => $exerciseId,
        'set_number' => $setNumber,
        'weight' => $weight,
        'reps' => $reps,
    ];
}

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->create();
    $this->exerciseIds = Exercise::query()->global()->orderBy('id')->limit(3)->pluck('id')->all();
    $this->workout = srBiSetWorkout($this->user, $this->exerciseIds);
});

// --- Lógica de rodadas (JS) ---------------------------------------------------

test('grupos viram um bloco; grupo inválido no cliente vira exercícios sozinhos', function () {
    $blocks = srJsOutput()['blocks'];

    expect($blocks['sem_grupo'])->toBe([
        ['names' => 'A', 'isGroup' => false, 'label' => null, 'indexes' => [0]],
        ['names' => 'B', 'isGroup' => false, 'label' => null, 'indexes' => [1]],
    ])
        ->and($blocks['bi_set_e_sozinho'])->toBe([
            ['names' => 'AB', 'isGroup' => true, 'label' => 'Bi-set', 'indexes' => [0, 1]],
            ['names' => 'C', 'isGroup' => false, 'label' => null, 'indexes' => [2]],
        ])
        ->and(array_column($blocks['grupo_de_um'], 'isGroup'))->toBe([false, false])
        ->and(array_column($blocks['nao_vizinhos'], 'isGroup'))->toBe([false, false, false])
        ->and($blocks['tri_set'][0])->toMatchArray(['names' => 'ABC', 'isGroup' => true, 'label' => 'Tri-set']);
});

test('bi-set 3×3 tem 3 rodadas com os 2 exercícios', function () {
    $biSet = srJsOutput()['bi_set_3x3'];

    expect($biSet['total'])->toBe(3)
        ->and($biSet['rounds'])->toBe(['AB', 'AB', 'AB'])
        ->and($biSet['current_inicio'])->toBe(1)
        ->and($biSet['current_apos_rodada_1'])->toBe(2)
        // Rodada salva só pela metade: continua nela (para tentar de novo).
        ->and($biSet['current_rodada_incompleta'])->toBe(1)
        ->and($biSet['current_fim'])->toBeNull()
        ->and($biSet['title'])->toBe('Bi-set · Rodada 1 de 3');
});

test('bi-set 4×3 tem a rodada 4 só com o primeiro exercício', function () {
    $biSet = srJsOutput()['bi_set_4x3'];

    expect($biSet['total'])->toBe(4)
        ->and($biSet['rounds'])->toBe(['AB', 'AB', 'AB', 'A'])
        ->and($biSet['current_rodada_4'])->toBe(4);
});

test('tri-set tem 3 exercícios por rodada e descansa pelo último exercício da rodada', function () {
    $triSet = srJsOutput()['tri_set'];

    expect($triSet['label'])->toBe('Tri-set')
        ->and($triSet['rounds'])->toBe(['ABC', 'ABC', 'ABC'])
        ->and($triSet['rest'])->toBe(90)
        ->and($triSet['rest_sem_c'])->toBe(60)
        ->and($triSet['rest_padrao'])->toBe(60);
});

test('"Finalizar exercício" tira o exercício das próximas rodadas; todos finalizados encerram o grupo', function () {
    $finish = srJsOutput()['finalizar_exercicio'];

    expect($finish['rounds'])->toBe(['A', 'A', 'A'])
        ->and($finish['current'])->toBe(2)
        ->and($finish['todos'])->toBeNull();
});

test('séries extras contam nas rodadas', function () {
    $extra = srJsOutput()['series_extras'];

    expect($extra['total'])->toBe(4)
        ->and($extra['rounds'])->toBe(['AB', 'AB', 'AB', 'B'])
        ->and($extra['current'])->toBe(4)
        // "+ Adicionar rodada extra": só quem chegou à última rodada e não foi encerrado.
        ->and($extra['rodada_extra'])->toBe('A')
        ->and($extra['rodada_extra_3x3'])->toBe('AB')
        ->and($extra['rodada_extra_um_encerrado'])->toBe('B')
        ->and($extra['rodada_extra_todos_encerrados'])->toBe('AB');
});

// --- Backend: registro da rodada ---------------------------------------------

test('o treino em andamento recebe o grupo de cada exercício', function () {
    $session = srActiveSession($this->user, $this->workout);

    $this->actingAs($this->user)
        ->get(route('workout-sessions.show', $session))
        ->assertInertia(fn (Assert $page) => $page
            ->component('WorkoutSessions/Active')
            ->where('exercises.0.superset_group', 1)
            ->where('exercises.1.superset_group', 1)
            ->where('exercises.2.superset_group', null)
        );
});

test('"Concluir rodada" online grava exatamente um SetLog por exercício da rodada, sem duplicar ao reenviar', function () {
    [$a, $b] = $this->exerciseIds;
    $session = srActiveSession($this->user, $this->workout);

    $round = [
        ['exercise_id' => $a, 'set_number' => 1, 'weight' => 40, 'reps' => 10],
        ['exercise_id' => $b, 'set_number' => 1, 'weight' => 20, 'reps' => 12],
    ];

    // As séries da rodada, uma depois da outra, como `completeRound()` envia.
    foreach ($round as $set) {
        $this->actingAs($this->user)
            ->from(route('workout-sessions.show', $session))
            ->post(route('workout-sessions.sets.store', $session), $set)
            ->assertRedirect(route('workout-sessions.show', $session))
            ->assertSessionHasNoErrors();
    }

    // "Tentar de novo" depois de uma falha reenvia a rodada: nada duplica.
    foreach ($round as $set) {
        $this->post(route('workout-sessions.sets.store', $session), $set)->assertSessionHasNoErrors();
    }

    expect($session->setLogs()->count())->toBe(2)
        ->and($session->setLogs()->where('exercise_id', $a)->where('set_number', 1)->count())->toBe(1)
        ->and($session->setLogs()->where('exercise_id', $b)->where('set_number', 1)->count())->toBe(1)
        ->and((float) $session->setLogs()->where('exercise_id', $b)->value('weight'))->toBe(20.0);
});

test('"Concluir rodada" offline sincroniza um SetLog por exercício da rodada, sem duplicar ao reenviar o lote', function () {
    [$a, $b] = $this->exerciseIds;
    $session = srActiveSession($this->user, $this->workout);

    $actions = [
        srLogSetAction($session, $a, 1, 40, 10, 'a1'),
        srLogSetAction($session, $b, 1, 20, 12, 'b1'),
        srLogSetAction($session, $a, 2, 40, 9, 'a2'),
        srLogSetAction($session, $b, 2, 20, 11, 'b2'),
    ];

    $this->actingAs($this->user)
        ->postJson(route('sync.workout-sessions'), ['actions' => $actions])
        ->assertOk();

    // Reconexão instável: o mesmo lote chega de novo.
    $this->postJson(route('sync.workout-sessions'), ['actions' => $actions])->assertOk();

    expect($session->setLogs()->count())->toBe(4);

    foreach ([[$a, 1], [$b, 1], [$a, 2], [$b, 2]] as [$exerciseId, $setNumber]) {
        expect($session->setLogs()->where('exercise_id', $exerciseId)->where('set_number', $setNumber)->count())
            ->toBe(1, "Série {$setNumber} do exercício {$exerciseId}");
    }

    expect($session->setLogs()->where('exercise_id', $b)->where('set_number', 2)->value('reps'))->toBe(11);
});

test('rodada repetida na fila com novos ids de cliente (nova tentativa) corrige no lugar, sem duplicar', function () {
    [$a, $b] = $this->exerciseIds;
    $session = srActiveSession($this->user, $this->workout);

    $this->actingAs($this->user)->postJson(route('sync.workout-sessions'), ['actions' => [
        srLogSetAction($session, $a, 1, 40, 10, 'try1-a'),
        srLogSetAction($session, $b, 1, 20, 12, 'try1-b'),
    ]])->assertOk();

    $this->postJson(route('sync.workout-sessions'), ['actions' => [
        srLogSetAction($session, $a, 1, 42.5, 10, 'try2-a'),
        srLogSetAction($session, $b, 1, 20, 12, 'try2-b'),
    ]])->assertOk();

    expect($session->setLogs()->count())->toBe(2)
        ->and((float) $session->setLogs()->where('exercise_id', $a)->where('set_number', 1)->value('weight'))->toBe(42.5);
});

// --- Frontend: contrato da tela ----------------------------------------------

test('a tela do treino mostra o grupo como um card de rodada e registra a rodada pelas rotas de sempre', function () {
    $active = file_get_contents(resource_path('js/Pages/WorkoutSessions/Active.vue'));
    $card = file_get_contents(resource_path('js/Components/WorkoutSessions/SupersetRoundCard.vue'));
    $section = file_get_contents(resource_path('js/Components/WorkoutSessions/SupersetRoundSection.vue'));

    expect($active)->toContain("from '@/Utils/supersetRounds'")
        ->and($active)->toContain('<SupersetRoundCard')
        // Fichas sem grupo continuam no card de sempre.
        ->and($active)->toContain('<ExerciseActiveCard')
        // Cada série da rodada pelo caminho de sempre: rota online ou `log_set` na fila.
        ->and($active)->toContain("route('workout-sessions.sets.store'")
        ->and($active)->toContain("type: 'log_set'")
        ->and($card)->toContain('Concluir rodada')
        ->and($card)->toContain('roundTitle(')
        ->and($section)->toContain('<OverloadSuggestionCard')
        ->and($section)->toContain('<LoadRepsStepper')
        ->and($section)->toContain("emit('edit-set'")
        ->and($section)->toContain("emit('finish-exercise'");
});
