<?php

use App\Enums\Equipment;
use App\Enums\MuscleGroup;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Services\ExerciseEquipmentService;
use App\Services\ProgressiveOverloadService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

function makeEquipmentExercise(string $name, ?Equipment $equipment = null): Exercise
{
    return Exercise::query()->create([
        'user_id' => null,
        'name' => $name,
        'primary_muscle_group' => MuscleGroup::Chest,
        'secondary_muscle_groups' => [],
        'equipment' => $equipment,
    ]);
}

function fakeGeminiJson(array $body): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => json_encode($body)]]]],
            ],
        ], 200),
    ]);
}

function geminiPromptText(Request $request): string
{
    return (string) data_get($request->data(), 'contents.0.parts.0.text');
}

/**
 * Ficha com um único exercício e uma sessão concluída registrando $weight kg,
 * para o serviço de progressão ter histórico.
 */
function workoutWithHistoryFor(User $user, Exercise $exercise, float $weight, int $reps = 12): Workout
{
    $workout = $user->workouts()->create(['name' => 'Treino Equipamento']);
    $workout->workoutExercises()->create([
        'exercise_id' => $exercise->id,
        'order' => 0,
        'target_sets' => 3,
        'target_reps' => '8-12',
    ]);

    $pastSession = $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now()->subDay()->subHour(),
        'completed_at' => now()->subDay(),
    ]);
    $pastSession->setLogs()->create([
        'exercise_id' => $exercise->id,
        'set_number' => 1,
        'weight' => $weight,
        'reps' => $reps,
        'rpe' => 7,
    ]);

    return $workout;
}

// ─── Classificação por palavra-chave ───────────────────────────────────────

test('o equipamento é identificado por palavras no nome do exercício', function (string $name, Equipment $expected) {
    expect(app(ExerciseEquipmentService::class)->classifyByKeywords($name))->toBe($expected);
})->with([
    ['Supino Inclinado com Halteres', Equipment::Dumbbells],
    ['Rosca Alternada Haltere', Equipment::Dumbbells],
    ['Supino Reto com Barra', Equipment::BarbellPlates],
    ['Agachamento no Smith', Equipment::BarbellPlates],
    ['Leg Press 45°', Equipment::PlateLoadedMachine],
    ['Agachamento Hack', Equipment::PlateLoadedMachine],
    ['Crucifixo Inverso na Máquina', Equipment::SelectorizedMachine],
    ['Tríceps Corda na Polia', Equipment::SelectorizedMachine],
    ['Crossover', Equipment::SelectorizedMachine],
    ['Remada Baixa no Cabo', Equipment::SelectorizedMachine],
    ['Mesa Flexora', Equipment::SelectorizedMachine],
    ['Swing com Kettlebell', Equipment::Kettlebell],
    ['Barra Fixa (Pegada Pronada)', Equipment::Bodyweight],
    ['Mergulho nas Paralelas', Equipment::Bodyweight],
    ['Flexão de Braços', Equipment::Bodyweight],
]);

test('um nome sem palavra-chave conhecida não é classificado por palavra-chave', function () {
    expect(app(ExerciseEquipmentService::class)->classifyByKeywords('Elevação Pélvica'))->toBeNull();
});

test('exercícios resolvidos por palavra-chave são salvos sem nenhuma chamada à IA', function () {
    Http::fake();
    $exercise = makeEquipmentExercise('Desenvolvimento com Halteres');

    app(ExerciseEquipmentService::class)->ensureClassified([$exercise]);

    expect($exercise->fresh()->equipment)->toBe(Equipment::Dumbbells);
    Http::assertNothingSent();
});

// ─── Fallback para a IA, uma única vez ─────────────────────────────────────

test('sem palavra-chave, a IA é consultada uma única vez (em lote) e a resposta fica salva', function () {
    $pelvic = makeEquipmentExercise('Elevação Pélvica');
    $crucifix = makeEquipmentExercise('Crucifixo Reto');
    $barbell = makeEquipmentExercise('Remada Curvada com Barra');

    fakeGeminiJson([
        'classifications' => [
            ['exercise_id' => $pelvic->id, 'equipment' => 'barra_anilhas'],
            ['exercise_id' => $crucifix->id, 'equipment' => 'halteres'],
        ],
    ]);

    $service = app(ExerciseEquipmentService::class);
    $service->ensureClassified([$pelvic, $crucifix, $barbell]);

    expect($pelvic->fresh()->equipment)->toBe(Equipment::BarbellPlates);
    expect($crucifix->fresh()->equipment)->toBe(Equipment::Dumbbells);
    expect($barbell->fresh()->equipment)->toBe(Equipment::BarbellPlates);

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) use ($pelvic, $barbell) {
        $prompt = geminiPromptText($request);

        // Só o que as palavras-chave não resolveram vai para a IA.
        return str_contains($prompt, (string) $pelvic->name)
            && ! str_contains($prompt, (string) $barbell->name)
            && str_contains($prompt, 'maquina_placas');
    });

    // Usar os mesmos exercícios de novo nunca pergunta à IA outra vez.
    $service->ensureClassified([$pelvic->fresh(), $crucifix->fresh(), $barbell->fresh()]);

    Http::assertSentCount(1);
});

test('um exercício já classificado nunca é reclassificado, nem por palavra-chave nem pela IA', function () {
    Http::fake();
    $exercise = makeEquipmentExercise('Supino com Halteres', Equipment::SelectorizedMachine);

    app(ExerciseEquipmentService::class)->ensureClassified([$exercise]);

    expect($exercise->fresh()->equipment)->toBe(Equipment::SelectorizedMachine);
    Http::assertNothingSent();
});

test('quando a IA não consegue classificar, o exercício fica como "outro" e não é perguntado de novo', function () {
    $exercise = makeEquipmentExercise('Movimento Misterioso');

    fakeGeminiJson([
        'classifications' => [
            ['exercise_id' => $exercise->id, 'equipment' => 'trampolim'],
        ],
    ]);

    $service = app(ExerciseEquipmentService::class);
    $service->ensureClassified([$exercise]);
    $service->ensureClassified([$exercise->fresh()]);

    expect($exercise->fresh()->equipment)->toBe(Equipment::Other);
    Http::assertSentCount(1);
});

test('se a IA estiver indisponível, o exercício continua sem classificação (tratado como "outro")', function () {
    Http::fake([
        '*' => Http::response(['error' => 'indisponível'], 500),
    ]);
    $exercise = makeEquipmentExercise('Movimento Misterioso');

    app(ExerciseEquipmentService::class)->ensureClassified([$exercise]);

    expect($exercise->fresh()->equipment)->toBeNull();
    expect($exercise->fresh()->resolvedEquipment())->toBe(Equipment::Other);
});

// ─── Classificação sob demanda, no uso ─────────────────────────────────────

test('iniciar um treino classifica os exercícios dele: palavra-chave na hora e IA uma vez para o resto', function () {
    $user = User::factory()->create();
    $dumbbell = makeEquipmentExercise('Rosca Martelo com Halteres');
    $unknown = makeEquipmentExercise('Elevação Pélvica');

    $workout = $user->workouts()->create(['name' => 'Treino Novo']);
    $workout->workoutExercises()->create(['exercise_id' => $dumbbell->id, 'order' => 0]);
    $workout->workoutExercises()->create(['exercise_id' => $unknown->id, 'order' => 1]);

    fakeGeminiJson([
        'classifications' => [
            ['exercise_id' => $unknown->id, 'equipment' => 'barra_anilhas'],
        ],
    ]);

    $this->actingAs($user)
        ->post(route('workouts.start', $workout))
        ->assertRedirect();

    expect($dumbbell->fresh()->equipment)->toBe(Equipment::Dumbbells);
    expect($unknown->fresh()->equipment)->toBe(Equipment::BarbellPlates);
    Http::assertSentCount(1);

    // Um segundo treino com os mesmos exercícios não gasta mais nenhuma chamada.
    $this->actingAs($user)->post(route('workouts.start', $workout));

    Http::assertSentCount(1);
});

test('a sessão ativa entrega o equipamento de cada exercício para o passo dos botões + e −', function () {
    Http::fake();
    $user = User::factory()->create();
    $dumbbell = makeEquipmentExercise('Supino com Halteres', Equipment::Dumbbells);
    $unclassified = makeEquipmentExercise('Elevação Pélvica');

    $workout = $user->workouts()->create(['name' => 'Treino']);
    $workout->workoutExercises()->create(['exercise_id' => $dumbbell->id, 'order' => 0]);
    $workout->workoutExercises()->create(['exercise_id' => $unclassified->id, 'order' => 1]);

    $session = $user->workoutSessions()->create(['workout_id' => $workout->id, 'started_at' => now()]);

    $this->actingAs($user)
        ->get(route('workout-sessions.show', $session))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('WorkoutSessions/Active')
            ->where('exercises.0.equipment', 'halteres')
            ->where('exercises.1.equipment', null));

    // Abrir a sessão nunca dispara classificação (nem chamada de IA).
    Http::assertNothingSent();
});

// ─── Progressão restrita a cargas válidas ──────────────────────────────────

test('o prompt de progressão informa o equipamento e a lista de cargas válidas próximas', function () {
    $user = User::factory()->create();
    $exercise = makeEquipmentExercise('Supino com Halteres', Equipment::Dumbbells);
    $workout = workoutWithHistoryFor($user, $exercise, weight: 10);

    fakeGeminiJson(['recommendations' => [[
        'exercise_id' => $exercise->id,
        'suggested_load' => 12,
        'suggested_reps' => 8,
        'rationale' => 'Suba para o próximo halter.',
        'confidence' => 'high',
    ]]]);

    $suggestions = app(ProgressiveOverloadService::class)->analyzeWorkout($user, $workout);

    expect($suggestions->first()->suggested_load)->toBe(12.0);

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) {
        $prompt = geminiPromptText($request);
        $compact = preg_replace('/\s+/', '', $prompt);

        return str_contains($prompt, '"equipment": "halteres"')
            && str_contains($prompt, '"load_mode": "por_halter"')
            && str_contains($compact, '"valid_loads":[7,8,9,10,12,14,16]')
            && str_contains($prompt, 'Progressão dupla');
    });
});

test('uma carga inexistente devolvida pela IA é arredondada para a válida mais próxima e registrada em log', function () {
    Log::spy();
    $user = User::factory()->create();
    $exercise = makeEquipmentExercise('Supino com Halteres', Equipment::Dumbbells);
    $workout = workoutWithHistoryFor($user, $exercise, weight: 10);

    fakeGeminiJson(['recommendations' => [[
        'exercise_id' => $exercise->id,
        'suggested_load' => 10.5,
        'suggested_reps' => 10,
        'rationale' => 'Aumente um pouco a carga.',
        'confidence' => 'high',
    ]]]);

    $suggestion = app(ProgressiveOverloadService::class)->analyzeWorkout($user, $workout)->first();

    expect($suggestion->suggested_load)->toBe(10.0);
    expect($suggestion->rationale)->toBe('Aumente um pouco a carga.');

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Carga sugerida pela IA arredondada para um valor válido do equipamento.', Mockery::on(
            fn (array $context) => $context['exercise_id'] === $exercise->id
                && $context['ai_load'] === 10.5
                && $context['rounded_load'] === 10.0
        ));
});

test('uma carga de barra fora dos pares de anilha é arredondada a partir da carga atual (empate fica com a menor)', function () {
    Log::spy();
    $user = User::factory()->create();
    $exercise = makeEquipmentExercise('Supino Reto com Barra', Equipment::BarbellPlates);
    $workout = workoutWithHistoryFor($user, $exercise, weight: 40);

    fakeGeminiJson(['recommendations' => [[
        'exercise_id' => $exercise->id,
        'suggested_load' => 42.5,
        'suggested_reps' => 8,
        'rationale' => 'Progrida a carga.',
        'confidence' => 'high',
    ]]]);

    $suggestion = app(ProgressiveOverloadService::class)->analyzeWorkout($user, $workout)->first();

    expect($suggestion->suggested_load)->toBe(40.0);
    Log::shouldHaveReceived('warning')->with('Carga sugerida pela IA arredondada para um valor válido do equipamento.', Mockery::any())->once();
});

test('uma carga válida devolvida pela IA passa intacta, sem log de arredondamento', function () {
    Log::spy();
    $user = User::factory()->create();
    $exercise = makeEquipmentExercise('Cadeira Extensora', Equipment::SelectorizedMachine);
    $workout = workoutWithHistoryFor($user, $exercise, weight: 45);

    fakeGeminiJson(['recommendations' => [[
        'exercise_id' => $exercise->id,
        'suggested_load' => 50,
        'suggested_reps' => 10,
        'rationale' => 'Suba uma placa.',
        'confidence' => 'medium',
    ]]]);

    $suggestion = app(ProgressiveOverloadService::class)->analyzeWorkout($user, $workout)->first();

    expect($suggestion->suggested_load)->toBe(50.0);
    Log::shouldNotHaveReceived('warning');
});

test('exercício sem classificação ("outro") mantém o comportamento atual: sem lista de cargas e sem arredondamento', function () {
    Log::spy();
    $user = User::factory()->create();
    $exercise = makeEquipmentExercise('Elevação Pélvica');
    $workout = workoutWithHistoryFor($user, $exercise, weight: 40);

    fakeGeminiJson(['recommendations' => [[
        'exercise_id' => $exercise->id,
        'suggested_load' => 42.5,
        'suggested_reps' => 8,
        'rationale' => 'Progrida a carga.',
        'confidence' => 'high',
    ]]]);

    $suggestion = app(ProgressiveOverloadService::class)->analyzeWorkout($user, $workout)->first();

    expect($suggestion->suggested_load)->toBe(42.5);
    Log::shouldNotHaveReceived('warning');

    // A análise de progressão nunca classifica equipamento: continua sendo
    // exatamente uma chamada de IA por análise.
    expect($exercise->fresh()->equipment)->toBeNull();
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => str_contains(geminiPromptText($request), '"valid_loads": null'));
});
