<?php

use App\Enums\MovementPattern;
use App\Enums\MuscleGroup;
use App\Models\Exercise;
use App\Services\ExerciseIdentityService;
use App\Services\ExerciseMovementPatternService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function makePatternExercise(string $name, ?MovementPattern $pattern = null): Exercise
{
    return Exercise::query()->create([
        'user_id' => null,
        'name' => $name,
        'primary_muscle_group' => MuscleGroup::Chest,
        'secondary_muscle_groups' => [],
        'movement_pattern' => $pattern,
    ]);
}

test('o padrão de movimento é deduzido por palavra-chave', function (string $name, MovementPattern $expected) {
    expect(app(ExerciseMovementPatternService::class)->classifyByKeywords($name))->toBe($expected);
})->with([
    ['Supino Reto com Barra', MovementPattern::HorizontalPush],
    ['Desenvolvimento com Halteres', MovementPattern::VerticalPush],
    ['Remada Curvada', MovementPattern::HorizontalPull],
    ['Puxada Frontal', MovementPattern::VerticalPull],
    ['Agachamento Livre', MovementPattern::Squat],
    ['Leg Press 45°', MovementPattern::Squat],
    ['Stiff', MovementPattern::HipHinge],
    ['Rosca Direta', MovementPattern::ElbowFlexion],
    ['Tríceps Testa', MovementPattern::ElbowExtension],
    ['Elevação Lateral', MovementPattern::LateralRaise],
    ['Panturrilha em Pé', MovementPattern::Calf],
    ['Mesa Flexora', MovementPattern::KneeFlexion],
    ['Crucifixo Inverso', MovementPattern::HorizontalPull],
]);

test('o que as palavras-chave não resolvem vai para a IA numa única chamada, e é salvo', function () {
    $a = makePatternExercise('Movimento Esquisito A');
    $b = makePatternExercise('Movimento Esquisito B');
    $keyword = makePatternExercise('Supino Inclinado');
    $done = makePatternExercise('Algo Já Classificado', MovementPattern::Core);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode([
            'classifications' => [
                ['exercise_id' => $a->id, 'movement_pattern' => 'puxar_vertical'],
                ['exercise_id' => $b->id, 'movement_pattern' => 'invalido'],
            ],
        ])]]]]]], 200),
    ]);

    app(ExerciseMovementPatternService::class)->ensureClassified([$a, $b, $keyword, $done]);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => str_contains((string) data_get($request->data(), 'contents.0.parts.0.text'), 'Movimento Esquisito A')
        && ! str_contains((string) data_get($request->data(), 'contents.0.parts.0.text'), 'Supino Inclinado'));

    expect($a->fresh()->movement_pattern)->toBe(MovementPattern::VerticalPull)
        ->and($b->fresh()->movement_pattern)->toBe(MovementPattern::Other)
        ->and($keyword->fresh()->movement_pattern)->toBe(MovementPattern::HorizontalPush)
        ->and($done->fresh()->movement_pattern)->toBe(MovementPattern::Core);

    // Já classificados: uma segunda passada não pergunta nada à IA.
    app(ExerciseMovementPatternService::class)->ensureClassified([$a->fresh(), $b->fresh()]);
    Http::assertSentCount(1);
});

test('o mesmo exercício é reconhecido apesar de maiúsculas, acentos, espaços e abreviações', function () {
    $identity = app(ExerciseIdentityService::class);

    expect($identity->sameExercise('Supino c/ Halteres', 'supino com halteres'))->toBeTrue()
        ->and($identity->sameExercise('Elevação  Lateral', 'ELEVACAO LATERAL'))->toBeTrue()
        ->and($identity->sameExercise('Supino Reto', 'Supino Inclinado'))->toBeFalse();
});
