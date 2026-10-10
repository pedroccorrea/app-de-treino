<?php

use App\DataTransferObjects\RepRange;

test('interpreta o texto livre de target_reps', function (?string $text, ?array $expected) {
    $range = RepRange::parse($text);

    expect($range === null ? null : [$range->min, $range->max])->toBe($expected);
})->with([
    'número único' => ['10', [10, 10]],
    'faixa com hífen' => ['8-10', [8, 10]],
    'faixa com hífen e espaços' => ['8 - 10', [8, 10]],
    'faixa com "a"' => ['8 a 10', [8, 10]],
    'faixa com travessão' => ['8–10', [8, 10]],
    'faixa invertida' => ['10-8', [8, 10]],
    'pirâmide (lista de números → menor ao maior)' => ['12/10/8', [8, 12]],
    'número com texto' => ['10 cada lado', [10, 10]],
    'séries x reps' => ['3x10', [10, 10]],
    'séries × faixa' => ['4 × 8-10', [8, 10]],
    'até a falha' => ['até a falha', null],
    'falha' => ['falha', null],
    'AMRAP' => ['AMRAP', null],
    'número implausível' => ['500', null],
    'só espaços' => ['   ', null],
    'vazio' => ['', null],
    'nulo' => [null, null],
]);

test('nenhum formato gera algo que não seja inteiro', function (?string $text) {
    $columns = RepRange::columnsFor($text);

    foreach ($columns as $value) {
        expect($value === null || is_int($value))->toBeTrue();
    }
})->with(['10', '8-10', '8 a 10', '8–10', '10-8', '12/10/8', 'até a falha', 'AMRAP', '10 cada lado', '', null]);

test('columnsFor devolve mínimo e máximo prontos para gravar', function () {
    expect(RepRange::columnsFor('8 a 10'))->toBe(['target_reps_min' => 8, 'target_reps_max' => 10])
        ->and(RepRange::columnsFor('até a falha'))->toBe(['target_reps_min' => null, 'target_reps_max' => null]);
});

test('fromWorkoutExercise prefere as colunas e interpreta o texto em registros antigos', function () {
    $new = (object) ['target_reps' => '8-10', 'target_reps_min' => 8, 'target_reps_max' => 10];
    $old = (object) ['target_reps' => '8-10', 'target_reps_min' => null, 'target_reps_max' => null];
    $noTarget = (object) ['target_reps' => 'até a falha', 'target_reps_min' => null, 'target_reps_max' => null];

    expect(RepRange::fromWorkoutExercise($new)?->toArray())->toBe(['min' => 8, 'max' => 10])
        ->and(RepRange::fromWorkoutExercise($old)?->toArray())->toBe(['min' => 8, 'max' => 10])
        ->and(RepRange::fromWorkoutExercise($noTarget))->toBeNull()
        ->and(RepRange::fromWorkoutExercise(null))->toBeNull();
});

test('clamp mantém as repetições dentro da faixa', function () {
    $range = new RepRange(8, 10);

    expect($range->clamp(15))->toBe(10)
        ->and($range->clamp(5))->toBe(8)
        ->and($range->clamp(9))->toBe(9)
        ->and($range->contains(10))->toBeTrue()
        ->and($range->contains(11))->toBeFalse();
});
