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

test('sw.js implements the offline mini-server (Phase 33): synthesizes Inertia pages and queues mutations from IndexedDB', function () {
    $response = $this->get('/sw.js');

    $content = $response->getContent();

    // O banco/stores do mini-servidor precisam ser idênticos aos definidos
    // em `resources/js/Utils/offlineStorage.js`, já que os dois mantêm
    // cópias independentes da mesma camada de acesso ao IndexedDB.
    expect($content)->toContain("const DB_NAME = 'ia-lift-offline'");
    expect($content)->toContain("const STORE_PROGRAMS = 'cached_programs'");

    // Navegação/Inertia GET para as telas offline-first monta o JSON no
    // formato esperado pelo Inertia a partir do IndexedDB, sem bater na rede.
    expect($content)->toContain('Dashboard');
    expect($content)->toContain("'Workouts/Index'");
    expect($content)->toContain("'Programs/Index'");
    expect($content)->toContain("'Programs/Show'");
    expect($content)->toContain('buildOfflinePageResponse');

    // Mutações offline (criar/editar/excluir programa ou treino) são
    // enfileiradas na sync_queue e aplicadas otimisticamente, com um
    // redirecionamento 303 forjado para o Inertia seguir sem erro.
    expect($content)->toContain('MUTATION_ROUTES');
    expect($content)->toContain('handleOfflineMutation');
    expect($content)->toContain('enqueueSyncAction');
    expect($content)->toContain('status: 303');
});
