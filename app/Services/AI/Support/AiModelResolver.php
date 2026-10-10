<?php

namespace App\Services\AI\Support;

use App\Enums\AiTask;

/**
 * Resolves which model string a provider driver should use for a given
 * AiTask, reading from config/services.php (services.{provider}.models.{task}).
 * When the task has no configured model — or no task is given at all — the
 * driver's own $default is used. Centralizing this here keeps every driver's
 * model-selection rule identical instead of duplicating the same config
 * lookup in each one.
 */
class AiModelResolver
{
    public static function resolve(string $provider, ?AiTask $task, string $default): string
    {
        $taskModel = $task !== null
            ? config("services.{$provider}.models.{$task->value}")
            : null;

        return (string) ($taskModel ?: $default);
    }
}
