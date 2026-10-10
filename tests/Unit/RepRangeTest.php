<?php

use App\DataTransferObjects\RepRange;

test('interpreta o texto livre de target_reps', function (?string $text, ?array $expected) {
    $range = RepRange::parse($text);

    expect($range === null ? null : [$range->min, $range->max])->toBe($expected);
})->with([
    'número único' => ['10', [10, 10]],
    'faixa com hífen' => ['8-10', [8, 10]],
    'faixa com "a"' => ['8 a 10', [8, 10]],
    'faixa com travessão' => ['8–12', [8, 12]],
    'pirâmide' => ['12/10/8', [8, 12]],
    'séries x reps' => ['3x10', [10, 10]],
    'séries × faixa' => ['4 × 8-10', [8, 10]],
    'até a falha' => ['falha', null],
    'vazio' => ['', null],
    'nulo' => [null, null],
]);

test('clamp mantém as repetições dentro da faixa', function () {
    $range = new RepRange(8, 10);

    expect($range->clamp(15))->toBe(10)
        ->and($range->clamp(5))->toBe(8)
        ->and($range->clamp(9))->toBe(9)
        ->and($range->contains(10))->toBeTrue()
        ->and($range->contains(11))->toBeFalse();
});
