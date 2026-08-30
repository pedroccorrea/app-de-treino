<?php

namespace App\Services\AI\Support;

use App\Enums\AiTask;

/**
 * Resolves the strict per-request HTTP timeout (in seconds) every AI driver
 * must respect for a given AiTask, reading from config/services.php
 * (services.ai.timeouts.*). FastText requests (overload/dashboard) back
 * screens that render synchronously while the user waits, so they get a very
 * short budget; Vision requests (photo scanning) upload a full image and
 * need more room. Requests without a task (or a task without a dedicated
 * budget) fall back to the Vision budget, the more permissive of the two.
 */
class AiTimeoutResolver
{
    public static function resolve(?AiTask $task): int
    {
        return match ($task) {
            AiTask::FastText => (int) config('services.ai.timeouts.fast_text', 6),
            default => (int) config('services.ai.timeouts.vision', 30),
        };
    }
}
