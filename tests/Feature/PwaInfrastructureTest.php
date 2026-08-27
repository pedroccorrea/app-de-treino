<?php

test('manifest.json is publicly accessible with the correct content-type', function () {
    $response = $this->get('/manifest.json');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/manifest+json');

    $manifest = json_decode($response->getContent(), true);

    expect($manifest['name'])->toBe('IA-LIFT - Meu App de Treino');
    expect($manifest['short_name'])->toBe('IA-LIFT');
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
