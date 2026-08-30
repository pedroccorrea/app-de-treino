<?php

namespace App\Services\AI\Support;

use App\Enums\AiTask;

/**
 * Resolves which model string a provider driver should use for a given
 * AiTask, reading from config/services.php. A task-specific model
 * (services.{provider}.models.{task}) always wins; if it isn't configured,
 * the provider's general-purpose model (services.{provider}.model) is used,
 * and finally $default when neither is set. Centralizing this here keeps
 * every driver's model-selection rule identical instead of duplicating the
 * same config lookup fallback chain in each one.
 */
class AiModelResolver
{
    public static function resolve(string $provider, ?AiTask $task, string $default): string
    {
        $taskModel = $task !== null
            ? config("services.{$provider}.models.{$task->value}")
            : null;

        $model = $taskModel ?: config("services.{$provider}.model");

        return (string) ($model ?: $default);
    }
}
