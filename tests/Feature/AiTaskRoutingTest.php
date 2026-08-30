<?php

use App\Enums\AiTask;
use App\Services\AI\AiManager;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/**
 * Validates that AiManager routes each request to the model configured for
 * its AiTask (see config/services.php `models` per provider), for both text
 * and image requests, across every driver and through failover.
 */
function fakeGeminiJsonResponse(array $body): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => json_encode($body)]]]],
            ],
        ], 200),
    ]);
}

function fakeClaudeJsonResponse(array $body): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                ['type' => 'text', 'text' => json_encode($body)],
            ],
        ], 200),
    ]);
}

beforeEach(function () {
    config([
        'services.gemini.model' => 'gemini-default-model',
        'services.gemini.models.vision' => 'gemini-vision-model',
        'services.gemini.models.fast_text' => 'gemini-fast-text-model',
        'services.claude.model' => 'claude-default-model',
        'services.claude.models.vision' => 'claude-vision-model',
        'services.claude.models.fast_text' => 'claude-fast-text-model',
        'services.ai.default_driver' => 'gemini',
        'services.ai.fallback_driver' => 'claude',
    ]);
});

test('AiManager routes an AiTask::Vision text request to the driver configured vision model', function () {
    fakeGeminiJsonResponse(['ok' => true]);

    app(AiManager::class)->generateStructured('qualquer prompt', task: AiTask::Vision);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'gemini-vision-model'));
});

test('AiManager routes an AiTask::FastText request to the driver configured fast-text model', function () {
    fakeGeminiJsonResponse(['ok' => true]);

    app(AiManager::class)->generateStructured('qualquer prompt', task: AiTask::FastText);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'gemini-fast-text-model'));
});

test('AiManager falls back to the provider general-purpose model when no AiTask is given', function () {
    fakeGeminiJsonResponse(['ok' => true]);

    app(AiManager::class)->generateStructured('qualquer prompt');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'gemini-default-model'));
});

test('AiManager routes an AiTask::Vision image request to the driver configured vision model', function () {
    fakeGeminiJsonResponse(['ok' => true]);
    $image = UploadedFile::fake()->create('ficha.jpg', 200, 'image/jpeg');

    app(AiManager::class)->analyzeImage($image, 'qualquer prompt', task: AiTask::Vision);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'gemini-vision-model'));
});

test('AiManager routes to the Claude driver configured fast-text model when it is the default driver', function () {
    config(['services.ai.default_driver' => 'claude']);
    fakeClaudeJsonResponse(['ok' => true]);

    app(AiManager::class)->generateStructured('qualquer prompt', task: AiTask::FastText);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.anthropic.com')
        && ($request['model'] ?? null) === 'claude-fast-text-model');
});

test('AiManager preserves AiTask routing when it fails over to the secondary driver', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => function () {
            throw new ConnectionException('cURL error 28: Operation timed out after 60000 milliseconds with 0 bytes received');
        },
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['ok' => true])]],
        ], 200),
    ]);

    app(AiManager::class)->generateStructured('qualquer prompt', task: AiTask::FastText);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.anthropic.com')
        && ($request['model'] ?? null) === 'claude-fast-text-model');
});
