<?php

use App\Enums\AiTask;
use App\Services\AI\Support\AiTimeoutResolver;

test('FastText requests never budget more than 6 seconds', function () {
    expect(AiTimeoutResolver::resolve(AiTask::FastText))->toBe(6);
});

test('Vision requests budget up to 180 seconds', function () {
    expect(AiTimeoutResolver::resolve(AiTask::Vision))->toBe(180);
});

test('a request without a task falls back to the Vision budget', function () {
    expect(AiTimeoutResolver::resolve(null))->toBe(180);
});

test('timeouts are configurable via services.ai.timeouts', function () {
    config([
        'services.ai.timeouts.fast_text' => 4,
        'services.ai.timeouts.vision' => 20,
    ]);

    expect(AiTimeoutResolver::resolve(AiTask::FastText))->toBe(4);
    expect(AiTimeoutResolver::resolve(AiTask::Vision))->toBe(20);
});
