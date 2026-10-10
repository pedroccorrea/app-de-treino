<?php

use App\Enums\Equipment;
use App\Enums\MovementPattern;
use App\Enums\MuscleGroup;
use App\Enums\PerformanceTrend;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSession;
use App\Services\ProgressiveOverloadService;
use App\Services\RecentPerformanceService;
use App\Services\ValidLoadService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/*
 * Phase 48 — a progressão parte do histórico recente do EXERCÍCIO
 * (referência = melhor desempenho recente), não do último treino.
 */

function hpExercise(string $name, ?Equipment $equipment = Equipment::BarbellPlates, MuscleGroup $muscle = MuscleGroup::Chest): Exercise
{
    return Exercise::query()->create([
        'user_id' => null,
        'name' => $name,
        'primary_muscle_group' => $muscle,
        'secondary_muscle_groups' => [],
        'equipment' => $equipment,
    ]);
}

/**
 * @param  array<int, array{0: Exercise, 1: ?string}>  $items  [exercício, target_reps]
 */
function hpWorkout(User $user, array $items, string $name = 'Treino A'): Workout
{
    $workout = $user->workouts()->create(['name' => $name]);

    foreach ($items as $order => [$exercise, $targetReps]) {
        $workout->workoutExercises()->create([
            'exercise_id' => $exercise->id,
            'order' => $order,
            'target_sets' => 3,
            'target_reps' => $targetReps,
        ]);
    }

    return $workout->fresh();
}

/**
 * @param  array<int, array{0: ?float, 1: int, 2?: float}>  $sets  [carga, reps, rpe?]
 */
function hpSession(User $user, ?Workout $workout, Exercise $exercise, Carbon $completedAt, array $sets): WorkoutSession
{
    $session = $user->workoutSessions()->create([
        'workout_id' => $workout?->id,
        'started_at' => $completedAt->copy()->subHour(),
        'completed_at' => $completedAt,
    ]);

    foreach ($sets as $index => $set) {
        $session->setLogs()->create([
            'exercise_id' => $exercise->id,
            'set_number' => $index + 1,
            'weight' => $set[0],
            'reps' => $set[1],
            'rpe' => $set[2] ?? null,
        ]);
    }

    return $session;
}

/**
 * Uma sessão por item, do mais antigo para o mais recente (um dia de
 * intervalo, terminando ontem).
 *
 * @param  array<int, array<int, array{0: ?float, 1: int}>>  $sessions
 */
function hpHistory(User $user, ?Workout $workout, Exercise $exercise, array $sessions): void
{
    $count = count($sessions);

    foreach ($sessions as $index => $sets) {
        hpSession($user, $workout, $exercise, now()->startOfDay()->subDays($count - $index)->addHours(18), $sets);
    }
}

function hpFakeAi(array $recommendations): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode(['recommendations' => $recommendations])]]]]],
        ], 200),
    ]);
}

function hpRecommendation(Exercise $exercise, float $load, int $reps, string $rationale = 'Siga progredindo.'): array
{
    return ['exercise_id' => $exercise->id, 'suggested_load' => $load, 'suggested_reps' => $reps, 'rationale' => $rationale, 'confidence' => 'high'];
}

function hpPrompt(?int $index = 0): string
{
    $requests = collect(Http::recorded())->map(fn (array $pair) => $pair[0])->values();

    return (string) data_get($requests[$index]->data(), 'contents.0.parts.0.text');
}

function hpAnalyze(User $user, Workout $workout)
{
    return app(ProgressiveOverloadService::class)->analyzeWorkout($user, $workout->fresh());
}

// ─── Critério 1: dia fraco isolado não derruba a sugestão ────────────────────

test('várias sessões boas e um último treino mais fraco: a sugestão volta à referência mesmo se a IA responder abaixo', function () {
    Log::spy();
    $user = User::factory()->create();
    $bench = hpExercise('Supino Reto com Barra');
    $workout = hpWorkout($user, [[$bench, '8-10']]);

    hpHistory($user, $workout, $bench, [[[40, 10]], [[40, 10], [40, 9]], [[40, 10]], [[30, 8]]]);

    hpFakeAi([hpRecommendation($bench, 30, 10, 'Reduza a carga para recuperar.')]);

    $suggestion = hpAnalyze($user, $workout)->first();

    expect($suggestion->suggested_load)->toBe(40.0)
        ->and($suggestion->suggested_reps)->toBe(10)
        ->and($suggestion->rationale)->toBe('Volte ao seu melhor recente (40 kg × 10): um treino mais fraco não muda a referência.')
        // "Anterior" continua sendo o último treino.
        ->and($suggestion->previous_load)->toBe(30.0)
        ->and($suggestion->previous_reps)->toBe(8);

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Carga sugerida pela IA abaixo da referência levada de volta à referência.', Mockery::on(
            fn (array $context) => $context['reference_load'] === 40.0 && $context['ai_load'] === 30.0 && $context['adjusted_load'] === 40.0
        ));

    // A âncora das cargas válidas é a referência, não o último treino.
    $prompt = hpPrompt();
    expect($prompt)->toMatch('/"reference_set": \{\s*"weight": 40(\.0)?,\s*"reps": 10,/')
        ->and($prompt)->toContain('"sessions_below_reference": 1')
        ->and($prompt)->toContain('"base_load": 40')
        ->and(preg_replace('/\s+/', '', $prompt))->toContain('"valid_loads":[25,30,35,40,45,50,55]');
});

test('dia fraco isolado: uma sugestão que progride a partir da referência passa intacta', function () {
    Log::spy();
    $user = User::factory()->create();
    $bench = hpExercise('Supino Reto com Barra');
    $workout = hpWorkout($user, [[$bench, '8-10']]);

    hpHistory($user, $workout, $bench, [[[40, 10]], [[40, 10]], [[30, 8]]]);

    hpFakeAi([hpRecommendation($bench, 45, 8, 'Você já domina 40 kg × 10: suba um par de anilhas.')]);

    $suggestion = hpAnalyze($user, $workout)->first();

    expect($suggestion->suggested_load)->toBe(45.0)
        ->and($suggestion->suggested_reps)->toBe(8)
        ->and($suggestion->rationale)->toBe('Você já domina 40 kg × 10: suba um par de anilhas.');

    Log::shouldNotHaveReceived('warning');
});

// ─── Critério 2: histórico subindo progride (progressão dupla + faixa) ───────

test('histórico subindo com a referência abaixo do topo da faixa: progride em repetições', function () {
    $user = User::factory()->create();
    $dumbbells = hpExercise('Supino Reto com Halteres', Equipment::Dumbbells);
    $workout = hpWorkout($user, [[$dumbbells, '8-12']]);

    hpHistory($user, $workout, $dumbbells, [[[20, 8]], [[20, 10]], [[22, 9]]]);

    hpFakeAi([hpRecommendation($dumbbells, 22, 10, 'Mais uma repetição com 22 kg.')]);

    $suggestion = hpAnalyze($user, $workout)->first();

    expect($suggestion->suggested_load)->toBe(22.0)
        ->and($suggestion->suggested_reps)->toBe(10);

    $prompt = hpPrompt();
    expect($prompt)->toContain('"trend": "subindo"')
        ->and($prompt)->toContain('"plan": "subir_repeticoes"')
        ->and($prompt)->toContain('"sessions_below_reference": 0');
});

test('histórico subindo com a referência no topo da faixa: progride para a próxima carga válida', function () {
    $user = User::factory()->create();
    $dumbbells = hpExercise('Supino Reto com Halteres', Equipment::Dumbbells);
    $workout = hpWorkout($user, [[$dumbbells, '8-12']]);

    hpHistory($user, $workout, $dumbbells, [[[20, 10]], [[20, 12]], [[22, 12]]]);

    hpFakeAi([hpRecommendation($dumbbells, 24, 8, 'Topo da faixa: suba para 24 kg.')]);

    $suggestion = hpAnalyze($user, $workout)->first();

    expect($suggestion->suggested_load)->toBe(24.0)
        ->and($suggestion->suggested_reps)->toBe(8);

    expect(hpPrompt())->toContain('"plan": "subir_carga"')
        ->and(preg_replace('/\s+/', '', hpPrompt()))->toContain('"valid_loads":[16,18,20,22,24,26,28]');
});

// ─── Critério 3: queda repetida ──────────────────────────────────────────────

test('queda repetida: a carga não passa da recente e o motivo em uma linha manda manter e recuperar as repetições', function () {
    Log::spy();
    $user = User::factory()->create();
    $bench = hpExercise('Supino Reto com Barra');
    $workout = hpWorkout($user, [[$bench, '8-10']]);

    hpHistory($user, $workout, $bench, [[[50, 10]], [[45, 8]], [[45, 7]], [[45, 6]]]);

    hpFakeAi([hpRecommendation($bench, 50, 10, 'Volte para 50 kg.')]);

    $suggestion = hpAnalyze($user, $workout)->first();

    expect($suggestion->suggested_load)->toBe(45.0)
        ->and($suggestion->suggested_reps)->toBe(10)
        ->and($suggestion->rationale)->toBe('Queda em 3 treinos seguidos: mantenha 45 kg e recupere as repetições antes de subir a carga.')
        ->and($suggestion->rationale)->not->toContain("\n");

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Carga sugerida pela IA acima da carga recente limitada por queda repetida.', Mockery::on(
            fn (array $context) => $context['recent_load'] === 45.0 && $context['ai_load'] === 50.0 && $context['adjusted_load'] === 45.0
        ));

    $prompt = hpPrompt();
    expect($prompt)->toContain('"plan": "manter_carga_recuperar_repeticoes"')
        ->and($prompt)->toContain('"sessions_below_reference": 3')
        ->and($prompt)->toContain('"base_load": 45');
});

test('queda repetida: uma redução sugerida pela IA é respeitada (nunca sobe)', function () {
    $user = User::factory()->create();
    $bench = hpExercise('Supino Reto com Barra');
    $workout = hpWorkout($user, [[$bench, '8-10']]);

    hpHistory($user, $workout, $bench, [[[50, 10]], [[45, 8]], [[45, 7]], [[45, 6]]]);

    hpFakeAi([hpRecommendation($bench, 40, 10)]);

    $suggestion = hpAnalyze($user, $workout)->first();

    expect($suggestion->suggested_load)->toBe(40.0)
        ->and($suggestion->rationale)->toBe('Queda em 3 treinos seguidos: mantenha 40 kg e recupere as repetições antes de subir a carga.');
});

test('abaixo do limiar (2 sessões seguidas abaixo da referência) ainda não é queda repetida: volta à referência', function () {
    $user = User::factory()->create();
    $bench = hpExercise('Supino Reto com Barra');
    $workout = hpWorkout($user, [[$bench, '8-10']]);

    hpHistory($user, $workout, $bench, [[[50, 10]], [[45, 8]], [[45, 7]]]);

    hpFakeAi([hpRecommendation($bench, 45, 8)]);

    $suggestion = hpAnalyze($user, $workout)->first();

    expect(ProgressiveOverloadService::REPEATED_DROP_SESSIONS)->toBe(3);
    expect($suggestion->suggested_load)->toBe(50.0)
        ->and($suggestion->suggested_reps)->toBe(10);
});

// ─── Critério 4: carga sempre válida, mesmo depois das redes de segurança ────

test('toda carga sugerida é válida para o equipamento depois das redes de segurança', function (Equipment $equipment, array $history, float $aiLoad, float $expected) {
    $user = User::factory()->create();
    $exercise = hpExercise('Exercício Teste', $equipment);
    $workout = hpWorkout($user, [[$exercise, '8-10']]);

    hpHistory($user, $workout, $exercise, $history);
    hpFakeAi([hpRecommendation($exercise, $aiLoad, 10)]);

    $suggestion = hpAnalyze($user, $workout)->first();

    expect($suggestion->suggested_load)->toBe($expected);
    expect(app(ValidLoadService::class)->isValid($equipment, $equipment->loadMode(), $expected, $suggestion->suggested_load))->toBeTrue();
})->with([
    'halteres, dia fraco, IA abaixo e fora da escada' => [Equipment::Dumbbells, [[[14, 10]], [[14, 10]], [[10, 8]]], 11.3, 14.0],
    'halteres, dia fraco, IA acima e fora da escada' => [Equipment::Dumbbells, [[[14, 10]], [[14, 10]], [[10, 8]]], 15.1, 16.0],
    'halteres, queda repetida, IA acima' => [Equipment::Dumbbells, [[[16, 10]], [[14, 8]], [[14, 8]], [[14, 7]]], 15.0, 14.0],
    'máquina de placas, dia fraco' => [Equipment::SelectorizedMachine, [[[45, 10]], [[45, 10]], [[35, 8]]], 41.0, 45.0],
    'máquina de placas, queda repetida' => [Equipment::SelectorizedMachine, [[[50, 10]], [[45, 8]], [[45, 8]], [[45, 7]]], 52.5, 45.0],
    'barra com anilhas, dia fraco' => [Equipment::BarbellPlates, [[[60, 10]], [[60, 10]], [[50, 8]]], 52.5, 60.0],
    'barra com anilhas, queda repetida' => [Equipment::BarbellPlates, [[[60, 10]], [[55, 8]], [[55, 8]], [[55, 7]]], 62.5, 55.0],
]);

// ─── Critério 5: edições e exclusões no histórico refletem na sugestão ───────

test('editar uma série do histórico muda a referência usada na sugestão seguinte', function () {
    $user = User::factory()->create();
    $machine = hpExercise('Leg Press', Equipment::Other, MuscleGroup::Quads);
    $workout = hpWorkout($user, [[$machine, '8-10']]);

    $best = hpSession($user, $workout, $machine, now()->subDays(5), [[100, 10]]);
    hpSession($user, $workout, $machine, now()->subDay(), [[80, 10]]);

    hpFakeAi([hpRecommendation($machine, 80, 10)]);
    expect(hpAnalyze($user, $workout)->first()->suggested_load)->toBe(100.0);

    // A série de 100 kg foi um erro de digitação: era 85 kg.
    $this->actingAs($user)
        ->patch(route('history.sets.update', [$best, $best->setLogs()->first()]), ['weight' => 85, 'reps' => 10])
        ->assertRedirect();

    expect(hpAnalyze($user, $workout)->first()->suggested_load)->toBe(85.0);
    expect(hpPrompt(1))->toMatch('/"reference_set": \{\s*"weight": 85(\.0)?,/');
});

test('excluir um treino do histórico muda a referência usada na sugestão seguinte', function () {
    $user = User::factory()->create();
    $machine = hpExercise('Leg Press', Equipment::Other, MuscleGroup::Quads);
    $workout = hpWorkout($user, [[$machine, '8-10']]);

    $testSession = hpSession($user, $workout, $machine, now()->subDays(3), [[150, 10]]);
    hpSession($user, $workout, $machine, now()->subDay(), [[80, 10]]);

    hpFakeAi([hpRecommendation($machine, 80, 10)]);
    expect(hpAnalyze($user, $workout)->first()->suggested_load)->toBe(150.0);

    // Treino feito só para testar o app: excluído do histórico (Phase 45).
    $this->actingAs($user)->delete(route('history.destroy', $testSession))->assertRedirect();

    $suggestion = hpAnalyze($user, $workout)->first();
    expect($suggestion->suggested_load)->toBe(80.0);
    expect(hpPrompt(1))->not->toContain('150');
});

test('excluir um exercício de um treino do histórico tira as séries dele do resumo', function () {
    $user = User::factory()->create();
    $machine = hpExercise('Leg Press', Equipment::Other, MuscleGroup::Quads);
    $other = hpExercise('Supino Reto com Barra');
    $workout = hpWorkout($user, [[$machine, '8-10']]);

    $session = hpSession($user, $workout, $machine, now()->subDays(3), [[150, 10]]);
    $session->setLogs()->create(['exercise_id' => $other->id, 'set_number' => 1, 'weight' => 40, 'reps' => 10]);
    hpSession($user, $workout, $machine, now()->subDay(), [[80, 10]]);

    $this->actingAs($user)->delete(route('history.exercises.destroy', [$session, $machine]))->assertRedirect();

    hpFakeAi([hpRecommendation($machine, 80, 10)]);
    expect(hpAnalyze($user, $workout)->first()->suggested_load)->toBe(80.0);
});

// ─── Critério 6: histórico em outra ficha x exercício sem histórico ──────────

test('exercício com histórico só em outra ficha recebe progressão normal; exercício sem histórico continua com a Phase 43', function () {
    $user = User::factory()->create();
    $bench = hpExercise('Supino Reto com Barra');
    // Já classificado: a classificação única da Phase 43 não entra na conta.
    $brandNew = hpExercise('Exercício Inédito', Equipment::Other, MuscleGroup::Abs);
    $brandNew->forceFill(['movement_pattern' => MovementPattern::Core])->save();

    $oldWorkout = hpWorkout($user, [[$bench, '8-10']], 'Programa Antigo');
    hpHistory($user, $oldWorkout, $bench, [[[40, 10]], [[42.5, 10]]]);

    $newWorkout = hpWorkout($user, [[$bench, '8-10'], [$brandNew, '10']], 'Programa Novo');
    $session = $user->workoutSessions()->create(['workout_id' => $newWorkout->id, 'started_at' => now()]);

    hpFakeAi([hpRecommendation($bench, 47.5, 8, 'Topo da faixa: suba um par de anilhas.')]);

    $suggestions = collect($this->actingAs($user)
        ->getJson(route('workout-sessions.overload-suggestions', $session))
        ->assertOk()
        ->json('suggestions'))->keyBy('exercise_id');

    expect($suggestions[$bench->id])->toMatchArray([
        'kind' => 'progressao',
        'suggested_load' => 47.5,
        'previous_load' => 42.5,
    ]);
    expect($suggestions[$brandNew->id])->toMatchArray([
        'kind' => 'primeira_vez',
        'suggested_load' => null,
    ]);

    // Uma única chamada (a da progressão); a "Primeira vez" não gasta IA.
    Http::assertSentCount(1);
    expect(hpPrompt())->toContain('Supino Reto com Barra')->not->toContain('Exercício Inédito');
});

// ─── Critério 7: prompt só com o resumo, limitado, e uma única chamada ───────

test('o prompt leva só o resumo de poucas sessões por exercício, nunca as séries cruas, numa única chamada', function () {
    $user = User::factory()->create();
    $bench = hpExercise('Supino Reto com Barra');
    $row = hpExercise('Remada Curvada com Barra', muscle: MuscleGroup::Back);
    $workout = hpWorkout($user, [[$bench, '8-10'], [$row, '8-10']]);

    $dates = [];

    foreach (range(0, 7) as $k) {
        $date = now()->startOfDay()->subDays(20 - $k)->addHours(18);
        $dates[] = $date->toDateString();
        $session = hpSession($user, $workout, $bench, $date, [[40, 10, 8.5], [40, 9], [40, 8]]);
        $session->setLogs()->create(['exercise_id' => $row->id, 'set_number' => 1, 'weight' => 50, 'reps' => 10]);
    }

    hpFakeAi([hpRecommendation($bench, 40, 10), hpRecommendation($row, 50, 10)]);

    expect(hpAnalyze($user, $workout))->toHaveCount(2);

    Http::assertSentCount(1);
    $prompt = hpPrompt();

    // Só as últimas OWN_HISTORY_SESSIONS sessões de cada exercício.
    $window = ProgressiveOverloadService::OWN_HISTORY_SESSIONS;
    foreach ($dates as $k => $date) {
        expect(str_contains($prompt, $date))->toBe($k >= count($dates) - $window, "sessão {$k}");
    }

    expect(substr_count($prompt, '"recent_sessions"'))->toBe(2)
        ->and($prompt)->not->toContain('set_number')
        ->and($prompt)->not->toContain('Histórico (mais recente primeiro)')
        // RPE entra quando foi registrado.
        ->and($prompt)->toContain('"rpe": 8.5');
});

// ─── Task 6: histórico próprio curto ganha parecidos de apoio, sem IA extra ──

test('histórico próprio curto é complementado com exercícios parecidos, sem chamada extra nem gravação', function () {
    $user = User::factory()->create();
    $incline = hpExercise('Supino Inclinado com Barra');
    $flat = hpExercise('Supino Reto com Barra');
    $workout = hpWorkout($user, [[$incline, '8-10']]);

    hpHistory($user, null, $flat, [[[60, 8]], [[62.5, 8]], [[65, 8]]]);
    hpSession($user, $workout, $incline, now()->subHours(3), [[50, 8]]);

    hpFakeAi([hpRecommendation($incline, 50, 9)]);

    expect(hpAnalyze($user, $workout)->first()->suggested_load)->toBe(50.0);

    Http::assertSentCount(1);
    $prompt = hpPrompt();
    expect($prompt)->toContain('"similar"')
        ->and($prompt)->toContain('Supino Reto com Barra')
        ->and($prompt)->toMatch('/"best_recent_set": \{\s*"weight": 65(\.0)?,/');

    // Classificação por palavra-chave só em memória: nada foi gravado.
    expect($incline->fresh()->movement_pattern)->toBeNull()
        ->and($flat->fresh()->movement_pattern)->toBeNull();
});

test('com histórico próprio suficiente, nenhum parecido entra no prompt', function () {
    $user = User::factory()->create();
    $incline = hpExercise('Supino Inclinado com Barra');
    $flat = hpExercise('Supino Reto com Barra');
    $workout = hpWorkout($user, [[$incline, '8-10']]);

    hpHistory($user, null, $flat, [[[60, 8]], [[65, 8]]]);
    hpHistory($user, $workout, $incline, [[[50, 8]], [[50, 9]], [[50, 10]]]);

    hpFakeAi([hpRecommendation($incline, 55, 8)]);
    hpAnalyze($user, $workout);

    expect(hpPrompt())->not->toContain('"similar"')->not->toContain('Supino Reto com Barra');
});

// ─── Resumo do histórico (peça reutilizável da Phase 43, estendida) ──────────

test('o resumo conta as sessões seguidas abaixo da referência, a partir da mais recente', function (array $history, int $expectedBelow, PerformanceTrend $trend) {
    $user = User::factory()->create();
    $bench = hpExercise('Supino Reto com Barra');

    hpHistory($user, null, $bench, $history);

    $summary = app(RecentPerformanceService::class)->summarize($user, [$bench->id], 5)->get($bench->id);

    expect($summary->sessions_below_reference)->toBe($expectedBelow)
        ->and($summary->trend)->toBe($trend);
})->with([
    'subindo (a mais recente é a referência)' => [[[[40, 8]], [[40, 10]], [[42.5, 9]]], 0, PerformanceTrend::Rising],
    'estável' => [[[[40, 10]], [[40, 10]], [[40, 10]]], 0, PerformanceTrend::Stable],
    'um dia fraco' => [[[[40, 10]], [[40, 10]], [[35, 8]]], 1, PerformanceTrend::Falling],
    'queda repetida' => [[[[50, 10]], [[45, 8]], [[45, 7]], [[45, 6]]], 3, PerformanceTrend::Falling],
    'recuperou depois de cair' => [[[[45, 8]], [[40, 8]], [[45, 8]]], 0, PerformanceTrend::Stable],
]);

test('exercício de peso corporal registrado só em repetições também tem resumo na progressão', function () {
    $user = User::factory()->create();
    $pushUp = hpExercise('Flexão de Braços', Equipment::Bodyweight);
    $workout = hpWorkout($user, [[$pushUp, '10-15']]);

    hpHistory($user, $workout, $pushUp, [[[null, 12]], [[null, 14]]]);

    hpFakeAi([hpRecommendation($pushUp, 0, 15)]);

    $suggestion = hpAnalyze($user, $workout)->first();

    expect($suggestion->suggested_load)->toBe(0.0)
        ->and($suggestion->suggested_reps)->toBe(15)
        ->and($suggestion->previous_load)->toBeNull()
        ->and($suggestion->previous_reps)->toBe(14);
});
