<?php

use App\Enums\Equipment;
use App\Enums\MovementPattern;
use App\Enums\MuscleGroup;
use App\Enums\PerformanceTrend;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSession;
use App\Services\RecentPerformanceService;
use App\Services\StartingLoadService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Equipamento e padrão de movimento ficam nulos por padrão, para que a
 * classificação por palavra-chave (sem IA) seja exercitada.
 */
function slExercise(string $name, MuscleGroup $muscle = MuscleGroup::Chest, ?Equipment $equipment = null, ?MovementPattern $pattern = null): Exercise
{
    return Exercise::query()->create([
        'user_id' => null,
        'name' => $name,
        'primary_muscle_group' => $muscle,
        'secondary_muscle_groups' => [],
        'equipment' => $equipment,
        'movement_pattern' => $pattern,
    ]);
}

/**
 * @param  array<int, array{0: Exercise, 1: ?string}>  $items  [exercício, target_reps]
 */
function slWorkout(User $user, string $name, array $items): Workout
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
 * Sessão concluída em $completedAt com uma série de trabalho por par
 * [carga, reps] informado.
 *
 * @param  array<int, array{0: float, 1: int}>  $sets
 */
function slCompletedSession(User $user, ?Workout $workout, Exercise $exercise, Carbon $completedAt, array $sets, bool $warmup = false): WorkoutSession
{
    $session = $user->workoutSessions()->create([
        'workout_id' => $workout?->id,
        'started_at' => $completedAt->copy()->subHour(),
        'completed_at' => $completedAt,
    ]);

    foreach ($sets as $index => [$weight, $reps]) {
        $session->setLogs()->create([
            'exercise_id' => $exercise->id,
            'set_number' => $index + 1,
            'weight' => $weight,
            'reps' => $reps,
            'is_warmup' => $warmup,
        ]);
    }

    return $session;
}

function slFakeAi(array $body): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => json_encode($body)]]]],
            ],
        ], 200),
    ]);
}

function slPrompt(Request $request): string
{
    return (string) data_get($request->data(), 'contents.0.parts.0.text');
}

/**
 * Prompts enviados à IA até agora, na ordem.
 *
 * @return list<string>
 */
function slSentPrompts(): array
{
    return collect(Http::recorded())->map(fn (array $pair) => slPrompt($pair[0]))->values()->all();
}

function slSuggestionsFor(User $user, Workout $workout): array
{
    $session = $user->workoutSessions()->create(['workout_id' => $workout->id, 'started_at' => now()]);

    return test()->actingAs($user)
        ->getJson(route('workout-sessions.overload-suggestions', $session))
        ->assertOk()
        ->json('suggestions');
}

// ─── Critério 1: exercício novo com parecido no histórico ────────────────────

test('exercício novo com parecido recebe carga inicial válida, com o motivo em uma linha e só o resumo curto no prompt', function () {
    $user = User::factory()->create();
    $barbell = slExercise('Supino Reto com Barra');
    $dumbbells = slExercise('Supino Reto com Halteres');

    slCompletedSession($user, null, $barbell, now()->subDays(3), [[40, 8], [40, 7], [35, 10]]);

    $workout = slWorkout($user, 'Treino Novo', [[$dumbbells, '8-10']]);

    slFakeAi(['suggestions' => [
        ['exercise_id' => $dumbbells->id, 'suggested_load' => 15.3, 'suggested_reps' => 10, 'based_on_exercise_id' => $barbell->id],
    ]]);

    $suggestions = slSuggestionsFor($user, $workout);

    expect($suggestions)->toHaveCount(1);
    expect($suggestions[0])->toMatchArray([
        'exercise_id' => $dumbbells->id,
        'kind' => 'carga_inicial',
        // 15,3 kg não existe em halteres: arredonda para o halter válido mais próximo.
        'suggested_load' => 16,
        'suggested_reps' => 10,
        'basis' => 'Baseado em Supino Reto com Barra (40 kg × 8)',
        'previous_load' => null,
    ]);

    // Uma única chamada (as classificações saíram por palavra-chave).
    Http::assertSentCount(1);
    $prompt = slSentPrompts()[0];
    expect($prompt)->toContain('Supino Reto com Barra')
        ->toContain('"best_recent_set"')
        ->toContain('"load_mode": "por_halter"')
        ->toContain('"load_mode": "total"')
        ->toContain('Halteres usam carga por halter bem menor que a barra')
        ->toContain('Na primeira sessão, seja conservador');

    // Nenhum dado salvo foi alterado além da classificação (antes nula).
    expect($barbell->fresh()->name)->toBe('Supino Reto com Barra');
    expect($dumbbells->fresh()->movement_pattern)->toBe(MovementPattern::HorizontalPush);
});

// ─── Critério 1: exercício novo sem nenhum parecido ──────────────────────────

test('exercício novo sem nenhum parecido não recebe número inventado e mostra a orientação de primeira vez', function () {
    $user = User::factory()->create();
    $squat = slExercise('Agachamento Livre', MuscleGroup::Quads);
    $curl = slExercise('Rosca Direta com Barra', MuscleGroup::Biceps);

    slCompletedSession($user, null, $squat, now()->subDays(2), [[80, 8]]);

    $workout = slWorkout($user, 'Treino Braço', [[$curl, '10']]);

    Http::fake();

    $suggestions = slSuggestionsFor($user, $workout);

    expect($suggestions)->toBe([[
        'exercise_id' => $curl->id,
        'suggested_load' => null,
        'suggested_reps' => null,
        'previous_load' => null,
        'previous_reps' => null,
        'rationale' => 'Primeira vez: escolha uma carga em que você faça as repetições com boa técnica',
        'confidence' => 'low',
        'kind' => 'primeira_vez',
        'basis' => null,
    ]]);

    Http::assertNothingSent();
});

test('usuário sem histórico nenhum recebe "Primeira vez" em todos os exercícios, sem chamar a IA', function () {
    $user = User::factory()->create();
    $workout = slWorkout($user, 'Primeiro Treino', [
        [slExercise('Supino Reto com Barra'), '8-12'],
        [slExercise('Remada Curvada', MuscleGroup::Back), '10'],
    ]);

    Http::fake();

    $suggestions = slSuggestionsFor($user, $workout);

    expect(collect($suggestions)->pluck('kind')->all())->toBe(['primeira_vez', 'primeira_vez']);
    expect(collect($suggestions)->pluck('suggested_load')->all())->toBe([null, null]);
    Http::assertNothingSent();
});

// ─── Critério 1 / Task 1: troca de programa mantém o histórico ───────────────

test('trocar de programa mantém o histórico dos exercícios que continuam, mesmo com grafia diferente', function () {
    $user = User::factory()->create();
    $bench = slExercise('Supino Reto com Barra', equipment: Equipment::BarbellPlates);

    $oldWorkout = slWorkout($user, 'Programa Antigo - A', [[$bench, '8-10']]);
    slCompletedSession($user, $oldWorkout, $bench, now()->subDays(5), [[40, 8]]);

    // Programa novo: a ficha escaneada criou outro registro com grafia
    // diferente ("c/", maiúsculas) — continua sendo o mesmo exercício.
    $sameBench = Exercise::query()->create([
        'user_id' => $user->id,
        'name' => 'SUPINO RETO C/ BARRA',
        'primary_muscle_group' => MuscleGroup::Chest,
        'secondary_muscle_groups' => [],
        'equipment' => Equipment::BarbellPlates,
    ]);
    $newWorkout = slWorkout($user, 'Programa Novo - A', [[$bench, '8-10'], [$sameBench, '8-10']]);

    Http::fake();

    $suggestions = slSuggestionsFor($user, $newWorkout);

    // Nenhum dos dois é tratado como exercício novo: nada de carga estimada
    // nem de "Primeira vez" (a ficha nova ainda não tem sessões próprias,
    // então a progressão da Phase 42 também não roda).
    expect($suggestions)->toBe([]);
    Http::assertNothingSent();

    // O histórico continua aparecendo na sessão da ficha nova.
    $session = $user->workoutSessions()->whereNull('completed_at')->latest('id')->first();
    $this->actingAs($user)
        ->get(route('workout-sessions.show', $session))
        ->assertInertia(fn (Assert $page) => $page->where("lastLogs.{$bench->id}.weight", 40));

    // Ficha que só tem a grafia nova: o histórico da antiga vem junto.
    $onlyAlias = slWorkout($user, 'Programa Novo - B', [[$sameBench, '8-10']]);
    expect(slSuggestionsFor($user, $onlyAlias))->toBe([]);
    $aliasSession = $user->workoutSessions()->whereNull('completed_at')->latest('id')->first();
    $this->actingAs($user)
        ->get(route('workout-sessions.show', $aliasSession))
        ->assertInertia(fn (Assert $page) => $page->where("lastLogs.{$sameBench->id}.weight", 40));

    // Nada salvo foi alterado: nomes e histórico intactos.
    expect($sameBench->fresh()->name)->toBe('SUPINO RETO C/ BARRA');
    expect($bench->fresh()->name)->toBe('Supino Reto com Barra');
    expect($bench->setLogs()->count())->toBe(1);
});

// ─── Critério 2 / Task 7: transição para a progressão normal ─────────────────

test('depois que o exercício novo tem histórico próprio, a progressão normal da Phase 42 assume', function () {
    $user = User::factory()->create();
    $barbell = slExercise('Supino Reto com Barra');
    $dumbbells = slExercise('Supino Reto com Halteres');

    slCompletedSession($user, null, $barbell, now()->subDays(10), [[40, 8]]);
    $workout = slWorkout($user, 'Treino Novo', [[$dumbbells, '8-10']]);

    // A IA responde conforme o prompt: carga inicial ou progressão normal.
    Http::fake(fn (Request $request) => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(
        str_contains(slPrompt($request), 'pela primeira vez')
            ? ['suggestions' => [
                ['exercise_id' => $dumbbells->id, 'suggested_load' => 14, 'suggested_reps' => 10, 'based_on_exercise_id' => $barbell->id],
            ]]
            : ['recommendations' => [
                ['exercise_id' => $dumbbells->id, 'suggested_load' => 16, 'suggested_reps' => 8, 'rationale' => 'Suba para o próximo halter.', 'confidence' => 'high'],
            ]]
    )]]]]]], 200));

    // Primeiro treino: o halter ainda é novo e já tem um parecido → carga inicial.
    expect(slSuggestionsFor($user, $workout)[0]['kind'])->toBe('carga_inicial');

    // Ele conclui a sessão com o halter.
    slCompletedSession($user, $workout, $dumbbells, now()->subDay(), [[14, 10], [14, 9]]);

    // Próximo treino: progressão normal, com o histórico do próprio exercício.
    $suggestions = slSuggestionsFor($user, $workout);

    expect($suggestions)->toHaveCount(1);
    expect($suggestions[0])->toMatchArray([
        'exercise_id' => $dumbbells->id,
        'kind' => 'progressao',
        'suggested_load' => 16,
        'previous_load' => 14,
        'basis' => null,
    ]);

    // Uma chamada de carga inicial no primeiro treino, uma de progressão no segundo.
    Http::assertSentCount(2);
    expect(slSentPrompts()[1])->toContain('sobrecarga progressiva')->not->toContain('pela primeira vez');
});

// ─── Critério 6 / Task 8: resumo do histórico recente dos parecidos ──────────

test('um treino isolado mais fraco (o mais recente) não baixa a referência enviada à IA nem a carga sugerida', function () {
    $user = User::factory()->create();
    $barbell = slExercise('Supino Reto com Barra');
    $dumbbells = slExercise('Supino Reto com Halteres');

    slCompletedSession($user, null, $barbell, now()->subDays(9), [[35, 8]]);
    slCompletedSession($user, null, $barbell, now()->subDays(6), [[40, 8], [37.5, 8]]);
    slCompletedSession($user, null, $barbell, now()->subDays(2), [[30, 8]]);

    $workout = slWorkout($user, 'Treino Novo', [[$dumbbells, '8-10']]);

    slFakeAi(['suggestions' => [
        ['exercise_id' => $dumbbells->id, 'suggested_load' => 16, 'suggested_reps' => 8, 'based_on_exercise_id' => $barbell->id],
    ]]);

    $suggestion = slSuggestionsFor($user, $workout)[0];

    expect($suggestion['basis'])->toBe('Baseado em Supino Reto com Barra (40 kg × 8)');
    expect($suggestion['suggested_load'])->toEqual(16);

    $prompt = slSentPrompts()[0];
    expect($prompt)->toMatch('/"best_recent_set": \{\s*"weight": 40(\.0)?,\s*"reps": 8,/');
    expect($prompt)->toContain('"trend": "caindo"');
});

test('o resumo de histórico recente escolhe a melhor sessão, limita as sessões e ignora aquecimento e sessões em andamento', function () {
    $user = User::factory()->create();
    $bench = slExercise('Supino Reto com Barra');

    slCompletedSession($user, null, $bench, now()->subDays(20), [[60, 8]]); // fora da janela de 3 sessões
    slCompletedSession($user, null, $bench, now()->subDays(9), [[35, 8]]);
    slCompletedSession($user, null, $bench, now()->subDays(6), [[40, 8], [40, 6]]);
    slCompletedSession($user, null, $bench, now()->subDays(2), [[30, 8]]);
    slCompletedSession($user, null, $bench, now()->subDay(), [[100, 5]], warmup: true);

    $inProgress = $user->workoutSessions()->create(['started_at' => now()]);
    $inProgress->setLogs()->create(['exercise_id' => $bench->id, 'set_number' => 1, 'weight' => 90, 'reps' => 8]);

    $summary = app(RecentPerformanceService::class)->summarize($user, [$bench->id])->get($bench->id);

    expect($summary->sessions)->toHaveCount(3);
    expect(array_column($summary->sessions, 'weight'))->toBe([30.0, 40.0, 35.0]);
    expect($summary->best)->toMatchArray(['weight' => 40.0, 'reps' => 8]);
    expect($summary->trend)->toBe(PerformanceTrend::Falling);
});

test('a tendência do histórico recente é calculada em PHP: subindo, estável ou caindo', function (array $weights, PerformanceTrend $expected) {
    $user = User::factory()->create();
    $bench = slExercise('Supino Reto com Barra');

    // $weights do mais antigo para o mais recente.
    foreach ($weights as $index => $weight) {
        slCompletedSession($user, null, $bench, now()->subDays(10 - $index), [[$weight, 8]]);
    }

    $summary = app(RecentPerformanceService::class)->summarize($user, [$bench->id])->get($bench->id);

    expect($summary->trend)->toBe($expected);
})->with([
    'subindo' => [[30, 35, 40], PerformanceTrend::Rising],
    'estável' => [[40, 40, 40.5], PerformanceTrend::Stable],
    'caindo' => [[40, 37.5, 32.5], PerformanceTrend::Falling],
    'sessão única' => [[40], PerformanceTrend::Stable],
]);

// ─── Critério 7 / Task 9: repetições coerentes ───────────────────────────────

test('as repetições sugeridas ficam inteiras e dentro da faixa da ficha mesmo se a IA responder fora dela', function (?string $targetReps, mixed $aiReps, int $expectedReps) {
    $user = User::factory()->create();
    $barbell = slExercise('Supino Reto com Barra');
    $dumbbells = slExercise('Supino Reto com Halteres');

    slCompletedSession($user, null, $barbell, now()->subDays(3), [[40, 8]]);
    $workout = slWorkout($user, 'Treino Novo', [[$dumbbells, $targetReps]]);

    slFakeAi(['suggestions' => [
        ['exercise_id' => $dumbbells->id, 'suggested_load' => 14, 'suggested_reps' => $aiReps, 'based_on_exercise_id' => $barbell->id],
    ]]);

    $suggestion = slSuggestionsFor($user, $workout)[0];

    expect($suggestion['kind'])->toBe('carga_inicial');
    expect($suggestion['suggested_reps'])->toBe($expectedReps);
})->with([
    'acima da faixa 8-10' => ['8-10', 15, 10],
    'abaixo da faixa 8-10' => ['8-10', 5, 8],
    'fracionário dentro da faixa' => ['8-10', 8.6, 9],
    'número único' => ['12', 8, 12],
    'IA sem reps, com faixa' => ['8 a 10', null, 8],
    'sem meta, IA respeitada' => [null, 9, 9],
    'sem meta e sem reps: padrão do movimento' => [null, null, 8],
]);

test('quando as repetições saem da faixa, a carga é reajustada para continuar coerente e válida', function () {
    $user = User::factory()->create();
    $barbell = slExercise('Supino Reto com Barra');
    $machine = slExercise('Supino na Máquina');

    slCompletedSession($user, null, $barbell, now()->subDays(3), [[40, 8]]);
    $workout = slWorkout($user, 'Treino Novo', [[$machine, '6-8']]);

    // 40 kg × 15 reps pensado pela IA → 8 reps pedem mais carga (Epley):
    // 40 × 1,5 / 1,2667 ≈ 47,4 kg → placa válida mais próxima: 45 kg.
    slFakeAi(['suggestions' => [
        ['exercise_id' => $machine->id, 'suggested_load' => 40, 'suggested_reps' => 15, 'based_on_exercise_id' => $barbell->id],
    ]]);

    $suggestion = slSuggestionsFor($user, $workout)[0];

    expect($suggestion['suggested_reps'])->toBe(8);
    expect($suggestion['suggested_load'])->toEqual(45);
});

// ─── Critério 8 / Task 10: troca de equipamento ──────────────────────────────

test('exercício novo de máquina com histórico só de um parecido com barra recebe carga válida para a máquina e o motivo cita a barra', function () {
    $user = User::factory()->create();
    $barbell = slExercise('Supino Reto com Barra');
    $machine = slExercise('Supino na Máquina');

    slCompletedSession($user, null, $barbell, now()->subDays(4), [[60, 8], [60, 6]]);
    $workout = slWorkout($user, 'Treino Máquina', [[$machine, '8-10']]);

    slFakeAi(['suggestions' => [
        ['exercise_id' => $machine->id, 'suggested_load' => 52, 'suggested_reps' => 10, 'based_on_exercise_id' => $barbell->id],
    ]]);

    $suggestion = slSuggestionsFor($user, $workout)[0];

    expect($machine->fresh()->equipment)->toBe(Equipment::SelectorizedMachine);
    // Máquina de placas anda de 5 em 5: 52 → 50.
    expect($suggestion['suggested_load'])->toEqual(50);
    expect($suggestion['basis'])->toBe('Baseado em Supino Reto com Barra (60 kg × 8)');

    $prompt = slSentPrompts()[0];
    expect($prompt)->toContain('"equipment": "maquina_placas"')
        ->toContain('"equipment": "barra_anilhas"')
        ->toContain('Máquinas guiadas costumam permitir mais carga que peso livre');
});

// ─── Critério 9 / Task 11: prompt curto e custo ──────────────────────────────

test('o prompt leva no máximo 5 parecidos com poucas sessões cada e não há chamada extra à IA por série', function () {
    $user = User::factory()->create();

    // 7 parecidos (mesmo padrão e grupo), cada um com 5 sessões. O parecido
    // $i é tanto mais antigo quanto maior o $i; a sessão $k idem.
    $similar = collect(range(0, 6))->map(fn (int $i) => slExercise("Supino Variação {$i}", equipment: Equipment::BarbellPlates));
    $dates = [];

    foreach ($similar as $i => $exercise) {
        foreach (range(0, 4) as $k) {
            $date = now()->startOfDay()->subDays(1 + $i * 10 + $k);
            $dates[$i][$k] = $date->toDateString();
            slCompletedSession($user, null, $exercise, $date, [[40 + $k, 8]]);
        }
    }

    $incline = slExercise('Supino Inclinado com Halteres');
    $machine = slExercise('Supino na Máquina');
    $workout = slWorkout($user, 'Treino Novo', [[$incline, '8-10'], [$machine, '8-10']]);

    slFakeAi(['suggestions' => [
        ['exercise_id' => $incline->id, 'suggested_load' => 14, 'suggested_reps' => 10, 'based_on_exercise_id' => $similar[0]->id],
        ['exercise_id' => $machine->id, 'suggested_load' => 45, 'suggested_reps' => 10, 'based_on_exercise_id' => $similar[0]->id],
    ]]);

    $session = $user->workoutSessions()->create(['workout_id' => $workout->id, 'started_at' => now()]);
    $this->actingAs($user)->getJson(route('workout-sessions.overload-suggestions', $session))->assertOk();

    // Os dois exercícios novos numa ÚNICA chamada.
    Http::assertSentCount(1);
    $prompt = slSentPrompts()[0];

    // 5 parecidos por exercício novo (2 exercícios novos → 10 resumos).
    expect(substr_count($prompt, '"best_recent_set"'))->toBe(2 * StartingLoadService::MAX_SIMILAR);

    foreach (range(0, 6) as $i) {
        $inPrompt = $i < StartingLoadService::MAX_SIMILAR;
        expect(str_contains($prompt, "Supino Variação {$i}"))->toBe($inPrompt);

        foreach (range(0, 4) as $k) {
            $expected = $inPrompt && $k < StartingLoadService::MAX_SESSIONS_PER_SIMILAR;
            expect(str_contains($prompt, $dates[$i][$k]))->toBe($expected, "parecido {$i}, sessão {$k}");
        }
    }

    // Registrar séries na sessão não dispara nenhuma chamada nova à IA.
    foreach (range(1, 3) as $setNumber) {
        $this->actingAs($user)
            ->post(route('workout-sessions.sets.store', $session), [
                'exercise_id' => $incline->id,
                'set_number' => $setNumber,
                'weight' => 14,
                'reps' => 10,
            ])
            ->assertRedirect();
    }

    Http::assertSentCount(1);
});
