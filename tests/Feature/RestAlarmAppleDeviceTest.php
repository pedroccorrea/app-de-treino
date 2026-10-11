<?php

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Phase 50 — no iPhone/iPad o app não toca nenhum áudio durante o treino
 * (o iOS interrompe a música de outros apps); no Android tudo segue igual.
 * O projeto não tem runner de testes JS, então `tests/js/restAlarm.check.mjs`
 * é executado pelo Node (um processo por aparelho simulado) e o resultado é
 * verificado aqui.
 */
function raJsOutput(string $scenario): array
{
    $node = (new ExecutableFinder)->find('node');

    if ($node === null) {
        test()->markTestSkipped('Node.js não encontrado para verificar os utilitários de áudio do descanso.');
    }

    $process = new Process([$node, base_path('tests/js/restAlarm.check.mjs'), $scenario], base_path());
    $process->mustRun();

    return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
}

const RA_VIBRATION_PATTERN = [400, 120, 400, 120, 400, 350, 400, 120, 400, 120, 400];

test('a detecção reconhece iPhone e iPad (inclusive o iPad que se identifica como Mac)', function () {
    $detected = raJsOutput('detect');

    foreach ([
        'iphone_safari', 'iphone_chrome', 'iphone_firefox', 'iphone_edge', 'iphone_standalone',
        'iphone_ua_only', 'ipad_legacy', 'ipad_as_mac', 'ipad_as_mac_ua_only', 'from_navigator_ipad',
    ] as $case) {
        expect($detected[$case])->toBeTrue("Deveria detectar iPhone/iPad em \"{$case}\"");
    }
});

test('a detecção não confunde Android, Mac sem toque nem computador com iPhone/iPad', function () {
    $detected = raJsOutput('detect');

    foreach ([
        'android_phone', 'android_tablet', 'mac_safari_no_touch', 'mac_chrome_no_touch',
        'windows_desktop', 'windows_touchscreen', 'from_navigator_mac', 'without_navigator',
    ] as $case) {
        expect($detected[$case])->toBeFalse("Não deveria detectar iPhone/iPad em \"{$case}\"");
    }
});

test('no iPhone e no iPad nenhuma função de áudio do descanso toca nada', function (string $device) {
    $steps = raJsOutput($device);

    $silent = [
        'audio_created' => [],
        'play' => [],
        'pause' => 0,
        'audio_session_writes' => [],
        'media_session' => [],
        'vibrate' => [],
    ];

    // Desbloqueios de "Iniciar treino", primeiro toque e "Concluir série",
    // início do descanso, fim do descanso e as funções de parar.
    foreach (['start_workout_unlock', 'later_unlocks', 'rest_started', 'alarm_played', 'stopped'] as $step) {
        expect($steps[$step])->toBe($silent, "Algum áudio foi acionado em \"{$step}\" ({$device})");
    }

    expect($steps['audio_session_while_ringing'])->toBe('auto')
        ->and($steps['audio_session_after_stop'])->toBe('auto');
})->with(['iphone', 'ipad-mac']);

test('no Android o desbloqueio, o loop de silêncio, o bipe, o audioSession e a vibração seguem como antes', function () {
    $steps = raJsOutput('android');

    $silence = ['src' => 'silence', 'muted' => false, 'loop' => true, 'volume' => 0.01];
    $mutedAlarm = ['src' => '/sounds/rest-alarm.wav', 'muted' => true, 'loop' => false, 'volume' => 1];
    $alarm = ['src' => '/sounds/rest-alarm.wav', 'muted' => false, 'loop' => false, 'volume' => 1];
    $metadata = ['metadata' => [
        'title' => 'Descanso em andamento',
        'artist' => 'Setwave',
        'album' => 'Cronômetro de descanso',
    ]];

    // "Iniciar treino": play/pause do silêncio e play mudo do alarme.
    $unlock = $steps['start_workout_unlock'];
    expect($unlock['audio_created'])->toBe(['silence', '/sounds/rest-alarm.wav'])
        ->and($unlock['play'])->toBe([$silence, $mutedAlarm])
        ->and($unlock['pause'])->toBe(2)
        ->and($unlock['audio_session_writes'])->toBe([])
        ->and($unlock['media_session'])->toBe([]);

    // Toques seguintes: o alarme já está liberado; o silêncio é desbloqueado de novo.
    expect(array_slice($steps['later_unlocks']['play'], 2))->toBe([$silence, $silence]);

    // Descanso: o loop toca e a Media Session é registrada.
    $rest = $steps['rest_started'];
    expect(end($rest['play']))->toBe($silence)
        ->and($rest['media_session'])->toBe([$metadata, ['playbackState' => 'playing']]);

    // Fim do descanso: para o loop, toca o bipe em 'playback' e vibra.
    $played = $steps['alarm_played'];
    expect(end($played['play']))->toBe($alarm)
        ->and($played['audio_session_writes'])->toBe(['playback'])
        ->and(array_slice($played['media_session'], 2))->toBe([$metadata, ['playbackState' => 'paused']])
        ->and($played['vibrate'])->toBe([RA_VIBRATION_PATTERN])
        ->and($steps['audio_session_while_ringing'])->toBe('playback');

    // Parar: corta a vibração e volta o audioSession para 'auto'.
    $stopped = $steps['stopped'];
    expect($stopped['audio_session_writes'])->toBe(['playback', 'auto'])
        ->and($stopped['vibrate'])->toBe([RA_VIBRATION_PATTERN, 0])
        ->and($stopped['play'])->toHaveCount(count($played['play']))
        ->and($steps['audio_session_after_stop'])->toBe('auto');
});
