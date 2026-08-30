<?php

use App\Exceptions\AiException;
use App\Services\AI\AiManager;
use App\Services\AI\Contracts\AiDriverInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

function fakeGeminiStructuredResponse(array $body): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => json_encode($body)]]]],
            ],
        ], 200),
    ]);
}

function fakeClaudeStructuredResponse(array $body): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                ['type' => 'text', 'text' => json_encode($body)],
            ],
        ], 200),
    ]);
}

test('AiManager is resolved from the AiDriverInterface contract', function () {
    expect(app(AiDriverInterface::class))->toBeInstanceOf(AiManager::class);
});

test('AiManager resolves the Gemini driver by default', function () {
    fakeGeminiStructuredResponse(['foo' => 'gemini-response']);

    $result = app(AiManager::class)->generateStructured('qualquer prompt');

    expect($result)->toBe(['foo' => 'gemini-response']);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'generativelanguage.googleapis.com'));
});

test('AiManager switches driver dynamically based on AI_DEFAULT_DRIVER', function () {
    config(['services.ai.default_driver' => 'claude']);
    fakeClaudeStructuredResponse(['foo' => 'claude-response']);

    $result = app(AiManager::class)->generateStructured('qualquer prompt');

    expect($result)->toBe(['foo' => 'claude-response']);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.anthropic.com'));
});

test('AiManager automatically fails over to the secondary driver when the primary throws', function () {
    config([
        'services.ai.default_driver' => 'gemini',
        'services.ai.fallback_driver' => 'claude',
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/*' => function () {
            throw new ConnectionException('cURL error 28: Operation timed out after 60000 milliseconds with 0 bytes received');
        },
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['foo' => 'claude-fallback'])]],
        ], 200),
    ]);

    $result = app(AiManager::class)->generateStructured('qualquer prompt');

    expect($result)->toBe(['foo' => 'claude-fallback']);
    // The primary (Gemini) request throws before a response is recorded, so
    // only the successful fallback call to Claude shows up in Http::recorded.
    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.anthropic.com'));
});

test('AiManager degrades with an AiException when both the primary and fallback drivers fail', function () {
    config([
        'services.ai.default_driver' => 'gemini',
        'services.ai.fallback_driver' => 'claude',
    ]);

    Http::fake(function () {
        throw new ConnectionException('cURL error 28: Operation timed out after 60000 milliseconds with 0 bytes received');
    });

    expect(fn () => app(AiManager::class)->generateStructured('qualquer prompt'))
        ->toThrow(AiException::class);
});

test('AiManager does not attempt a fallback when none is configured', function () {
    config([
        'services.ai.default_driver' => 'gemini',
        'services.ai.fallback_driver' => null,
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/*' => function () {
            throw new ConnectionException('cURL error 28: Operation timed out after 60000 milliseconds with 0 bytes received');
        },
    ]);

    expect(fn () => app(AiManager::class)->generateStructured('qualquer prompt'))
        ->toThrow(AiException::class);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.anthropic.com'));
});
