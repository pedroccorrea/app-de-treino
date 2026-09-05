<?php

test('manifest.json is publicly accessible with the correct content-type', function () {
    $response = $this->get('/manifest.json');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/manifest+json');

    $manifest = json_decode($response->getContent(), true);

    expect($manifest['name'])->toBe('Setwave — Treino e Progressão');
    expect($manifest['short_name'])->toBe('Setwave');
    expect($manifest['display'])->toBe('standalone');
    expect($manifest['background_color'])->toBe('#0B0F17');
    expect($manifest['theme_color'])->toBe('#8B5CF6');
    expect($manifest['orientation'])->toBe('portrait-primary');

    expect($manifest['icons'])->toBeArray()->not->toBeEmpty();
    expect(collect($manifest['icons'])->pluck('sizes')->all())
        ->toContain('192x192')
        ->toContain('512x512');
});

test('sw.js is publicly accessible with the correct content-type', function () {
    $response = $this->get('/sw.js');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/javascript');
    expect($response->getContent())->toContain("addEventListener('install'");
});

test('sw.js intercepts Inertia requests and caches build assets for offline use', function () {
    $response = $this->get('/sw.js');

    $content = $response->getContent();

    // Requisições do Inertia devem ser reconhecidas pelo cabeçalho X-Inertia
    // e ter um fallback de cache quando a rede falhar.
    expect($content)->toContain("request.headers.get('X-Inertia')");
    expect($content)->toContain('networkFirst');

    // Pacotes compilados pelo Vite (hash no nome do arquivo) ficam em cache
    // permanente (estratégia cache-first).
    expect($content)->toContain('/build/assets/');
    expect($content)->toContain('cacheFirst');

    // Navegação de documento completo (F5 sem internet) cai para o app shell
    // em cache em vez do erro nativo de conexão do navegador.
    expect($content)->toContain("request.mode === 'navigate'");
});
