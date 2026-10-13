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
 * Phase 54: exercícios combinados — navegação, descanso, reabrir o treino e
 * offline.
 *
 * O cálculo de onde o treino reabre (`resumePosition` em
 * `resources/js/Utils/supersetRounds.js`) é verificado pelo Node via
 * `tests/js/sessionResume.check.mjs`. No backend nada mudou: a posição é
 * derivada só das séries já feitas (`setLogs`) e da marca de "Finalizar
 * exercício", que vive no registro local da sessão ativa (IndexedDB).
 */

function rpJsOutput(): array
{
    $node = (new ExecutableFinder)->find('node');

    if ($node === null) {
        test()->markTestSkipped('Node.js não encontrado para verificar a lógica JS da reabertura do treino.');
    }

    $process = new Process([$node, base_path('tests/js/sessionResume.check.mjs')], base_path());
    $process->mustRun();

    return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * Ficha com um bi-set (A+B) e um exercício sozinho (C).
 */
function rpBiSetWorkout(User $user, array $exerciseIds): Workout
{
    [$a, $b, $c] = $exerciseIds;

    $workout = $user->workouts()->create(['name' => 'Treino Bi-set']);
    $workout->exercises()->attach($a, ['order' => 0, 'target_sets' => 3, 'target_reps' => '10', 'rest_seconds' => 60, 'superset_group' => 1]);
    $workout->exercises()->attach($b, ['order' => 1, 'target_sets' => 3, 'target_reps' => '10', 'rest_seconds' => 90, 'superset_group' => 1]);
    $workout->exercises()->attach($c, ['order' => 2, 'target_sets' => 3, 'target_reps' => '10', 'rest_seconds' => 60]);

    return $workout;
}

function rpActiveSession(User $user, Workout $workout): WorkoutSession
{
    return $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now()->subMinutes(10),
        'completed_at' => null,
    ]);
}

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->create();
    $this->exerciseIds = Exercise::query()->global()->orderBy('id')->limit(3)->pluck('id')->all();
});

// --- Cálculo da posição ao reabrir (JS) ---------------------------------------

test('ficha sem grupo com séries feitas no meio do 2º exercício reabre no 2º exercício, na série certa', function () {
    $resume = rpJsOutput()['sem_grupo'];

    expect($resume['inicio'])->toMatchArray(['index' => 0, 'name' => 'A', 'setNumber' => 1])
        ->and($resume['meio_do_segundo'])->toMatchArray(['index' => 1, 'name' => 'B', 'setNumber' => 2, 'round' => null])
        // Série do meio sem registro: volta para ela.
        ->and($resume['serie_pulada'])->toMatchArray(['index' => 0, 'setNumber' => 2])
        ->and($resume['com_serie_extra'])->toMatchArray(['index' => 0, 'setNumber' => 4])
        // Ficha antiga do IndexedDB, sem `superset_group`: igual a sem grupo.
        ->and($resume['ficha_antiga'])->toMatchArray(['index' => 1, 'setNumber' => 3]);
});

test('bi-set com a rodada 1 feita reabre na rodada 2', function () {
    $resume = rpJsOutput()['bi_set'];

    expect($resume['inicio'])->toMatchArray(['index' => 0, 'round' => 1])
        ->and($resume['rodada_1_feita'])->toMatchArray(['index' => 0, 'blockIndex' => 0, 'round' => 2])
        // Rodada salva pela metade: continua nela.
        ->and($resume['rodada_incompleta'])->toMatchArray(['index' => 0, 'round' => 1])
        ->and($resume['um_finalizado'])->toMatchArray(['index' => 0, 'round' => 2])
        // Grupo terminado: segue para o próximo bloco, na série pendente.
        ->and($resume['grupo_feito'])->toMatchArray(['index' => 2, 'blockIndex' => 1, 'name' => 'C', 'setNumber' => 2])
        ->and($resume['sozinho_feito_grupo_no_fim'])->toMatchArray(['index' => 1, 'round' => 3]);
});

test('exercício finalizado com "Finalizar exercício" não reaparece ao reabrir', function () {
    $output = rpJsOutput();

    expect($output['sem_grupo']['primeiro_finalizado'])->toMatchArray(['index' => 1, 'name' => 'B', 'setNumber' => 1])
        ->and($output['sem_grupo']['tudo_feito_com_finalizado'])->toMatchArray(['index' => 2, 'setNumber' => null])
        // Os dois do bi-set finalizados: o grupo terminou.
        ->and($output['bi_set']['grupo_todo_finalizado'])->toMatchArray(['index' => 2, 'name' => 'C', 'setNumber' => 1]);
});

test('treino todo feito reabre no último bloco', function () {
    $output = rpJsOutput();

    expect($output['sem_grupo']['tudo_feito'])->toMatchArray(['index' => 2, 'name' => 'C', 'round' => null, 'setNumber' => null])
        ->and($output['bi_set']['tudo_feito'])->toMatchArray(['index' => 2, 'blockIndex' => 1])
        // Último bloco é um grupo: o primeiro exercício dele.
        ->and($output['bi_set']['tudo_feito_grupo_no_fim'])->toMatchArray(['index' => 1, 'blockIndex' => 1, 'round' => null])
        ->and($output['vazio'])->toMatchArray(['index' => 0]);
});

test('o progresso do grupo pelas rodadas (stepper e menu lateral)', function () {
    $progress = rpJsOutput()['progresso'];

    expect($progress['inicio'])->toBe(['current' => 1, 'total' => 3, 'done' => 0])
        ->and($progress['rodada_2'])->toBe(['current' => 2, 'total' => 3, 'done' => 1])
        ->and($progress['fim'])->toBe(['current' => null, 'total' => 3, 'done' => 3]);
});

// --- Backend: o que a reabertura usa -------------------------------------------

test('reabrir o treino traz as séries já feitas e o grupo de cada exercício', function () {
    [$a, $b] = $this->exerciseIds;
    $workout = rpBiSetWorkout($this->user, $this->exerciseIds);
    $session = rpActiveSession($this->user, $workout);

    $session->setLogs()->create(['exercise_id' => $a, 'set_number' => 1, 'weight' => 40, 'reps' => 10]);
    $session->setLogs()->create(['exercise_id' => $b, 'set_number' => 1, 'weight' => 20, 'reps' => 12]);

    $this->actingAs($this->user)
        ->get(route('workout-sessions.show', $session))
        ->assertInertia(fn (Assert $page) => $page
            ->component('WorkoutSessions/Active')
            ->has('setLogs', 2)
            ->where('exercises.0.superset_group', 1)
            ->where('exercises.1.superset_group', 1)
            ->where('exercises.2.superset_group', null)
            // A marca de "Finalizar exercício" não existe no servidor: vem do IndexedDB.
            ->missing('finishedExerciseIds')
        );
});

test('rodada concluída offline e reenviada ao sincronizar não duplica', function () {
    [$a, $b] = $this->exerciseIds;
    $workout = rpBiSetWorkout($this->user, $this->exerciseIds);
    $session = rpActiveSession($this->user, $workout);

    $actions = collect([[$a, 40, 10], [$b, 20, 12]])->map(fn ($set, $i) => [
        'uuid' => "rp-action-{$i}",
        'type' => 'log_set',
        'client_session_id' => "session-{$session->id}",
        'client_set_id' => "rp-set-{$i}",
        'session_id' => $session->id,
        'occurred_at' => now()->toISOString(),
        'exercise_id' => $set[0],
        'set_number' => 1,
        'weight' => $set[1],
        'reps' => $set[2],
    ])->all();

    $this->actingAs($this->user)->postJson(route('sync.workout-sessions'), ['actions' => $actions])->assertOk();
    $this->postJson(route('sync.workout-sessions'), ['actions' => $actions])->assertOk();

    expect($session->setLogs()->count())->toBe(2)
        ->and($session->setLogs()->where('exercise_id', $a)->where('set_number', 1)->count())->toBe(1)
        ->and($session->setLogs()->where('exercise_id', $b)->where('set_number', 1)->count())->toBe(1);
});

// --- Service Worker ------------------------------------------------------------

test('o treino offline do Service Worker traz o grupo, as séries feitas e os exercícios finalizados', function () {
    $sw = file_get_contents(resource_path('pwa/sw.js'));

    preg_match('/async function buildActiveSessionProps\(params\) \{(.*?)\n\}/s', $sw, $match);
    $build = $match[1] ?? '';

    expect($sw)->toContain("const CACHE_VERSION = 'v13';")
        ->and($build)->not->toBe('')
        // Ficha antiga no IndexedDB sem o campo = sem grupo.
        ->and($build)->toContain('superset_group: exercise.superset_group ?? null')
        ->and($build)->toContain('setLogs: await collectSessionSetLogs(session)')
        ->and($build)->toContain('finishedExerciseIds: Array.isArray(session.finished_exercise_ids) ? session.finished_exercise_ids : []');
});

// --- Frontend: contrato da tela ------------------------------------------------

test('a tela do treino reabre pela posição calculada e guarda o "Finalizar exercício" no registro local', function () {
    $active = file_get_contents(resource_path('js/Pages/WorkoutSessions/Active.vue'));
    $storage = file_get_contents(resource_path('js/Utils/offlineStorage.js'));

    expect($active)->toContain('resumePosition(sortedExercises.value, roundStatus)')
        ->and($active)->toContain('finishedExerciseIds: { type: Array')
        ->and($active)->toContain('recordActiveSessionFinishedExercise(exercise.id, true)')
        ->and($active)->toContain('recordActiveSessionFinishedExercise(exId, false)')
        ->and($storage)->toContain('export function recordActiveSessionFinishedExercise(')
        ->and($storage)->toContain('finished_exercise_ids');
});

test('stepper, menu lateral e descanso tratam o grupo como um bloco', function () {
    $active = file_get_contents(resource_path('js/Pages/WorkoutSessions/Active.vue'));
    $stepper = file_get_contents(resource_path('js/Components/WorkoutSessions/ExerciseStepper.vue'));
    $drawer = file_get_contents(resource_path('js/Components/WorkoutSessions/ExerciseOverviewDrawer.vue'));
    $overlay = file_get_contents(resource_path('js/Components/WorkoutSessions/RestTimerOverlay.vue'));

    expect($active)->toContain(':steps="stepperSteps"')
        ->and($active)->toContain(':blocks="drawerBlocks"')
        ->and($active)->toContain(':next-round-title="nextRoundTitle"')
        ->and($active)->toContain('roundTitle(currentGroup.value.label')
        // Sem grupo: textos de sempre.
        ->and($stepper)->toContain('Exercício {{ currentStep + 1 }} de {{ steps.length }}')
        ->and($stepper)->toContain('roundTitle(')
        ->and($drawer)->toContain('<BaseBadge tone="accent">{{ block.label }}</BaseBadge>')
        ->and($drawer)->toContain("\$emit('select', block.index)")
        ->and($overlay)->toContain('Próximo: {{ nextRoundTitle }}')
        ->and($overlay)->toContain('Próxima: Série {{ nextSetNumber }}');
});
