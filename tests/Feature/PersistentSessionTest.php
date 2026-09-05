<?php

test('a sessão é configurada para longa duração (login persistente de até 1 ano)', function () {
    expect(config('session.lifetime'))->toBe(525600)
        ->and(config('session.expire_on_close'))->toBeFalse();
});

test('o valor padrão de SESSION_LIFETIME no .env corresponde a 1 ano em minutos', function () {
    $envPath = base_path('.env');

    expect(file_exists($envPath))->toBeTrue();

    $env = file_get_contents($envPath);

    expect($env)->toContain('SESSION_LIFETIME=525600');
});
