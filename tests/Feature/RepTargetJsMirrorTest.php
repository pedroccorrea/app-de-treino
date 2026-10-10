<?php

use App\DataTransferObjects\RepRange;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Phase 47 — o frontend/offline interpreta fichas antigas com o espelho JS
 * da regra (`resources/js/Utils/repTarget.js`). O projeto não tem runner de
 * testes JS, então o script `tests/js/repTarget.check.mjs` é executado pelo
 * Node e o resultado é comparado aqui com o RepRange (PHP).
 */
function rtJsMirrorOutput(): array
{
    $node = (new ExecutableFinder)->find('node');

    if ($node === null) {
        test()->markTestSkipped('Node.js não encontrado para verificar o espelho JS da regra de repetições.');
    }

    $process = new Process([$node, base_path('tests/js/repTarget.check.mjs')], base_path());
    $process->mustRun();

    return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
}

test('o utilitário JS interpreta a meta exatamente como o RepRange do backend', function () {
    $output = rtJsMirrorOutput();

    foreach ($output['parse'] as $text => $jsRange) {
        $phpRange = RepRange::parse($text === 'null' ? null : (string) $text);

        expect($jsRange)->toBe($phpRange?->toArray(), "Divergência para \"{$text}\"");
    }
});

test('o REPS inicial segue a ordem sugestão da IA → última vez → mínimo da faixa → 10, sempre inteiro', function () {
    $initial = rtJsMirrorOutput()['initial'];

    expect($initial)->toBe([
        'suggestion_in_range' => 9,
        'suggestion_clamped' => 10,
        'suggestion_rounded' => 9,
        'no_suggestion_with_history' => 7,
        'suggestion_without_reps' => 7,
        'no_suggestion_no_history' => 8,
        'legacy_offline_no_history' => 8,
        'no_numeric_target' => 10,
        'sw_default_target' => 10,
    ]);
});

test('a meta aparece como faixa onde já era exibida e textos estranhos ficam como escritos', function () {
    $format = rtJsMirrorOutput()['format'];

    expect($format['8-10'])->toBe('8–10')
        ->and($format['8 a 10'])->toBe('8–10')
        ->and($format['10-8'])->toBe('8–10')
        ->and($format['10'])->toBe('10')
        ->and($format['12/10/8'])->toBe('12/10/8')
        ->and($format['até a falha'])->toBe('até a falha');
});
