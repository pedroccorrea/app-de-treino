<?php

namespace App\Services\AI\Contracts;

use App\Enums\AiTask;
use Illuminate\Http\UploadedFile;

/**
 * Contract every pluggable AI provider (Gemini, Claude, ...) must implement
 * so that AiManager and the domain services that depend on it never couple
 * to a specific vendor's SDK or HTTP shape.
 */
interface AiDriverInterface
{
    /**
     * Sends a text prompt and returns the response decoded as a structured
     * array. Implementations are responsible for stripping markdown code
     * fences around the JSON payload before decoding it. $task lets the
     * driver pick a model suited to the workload (see AiModelResolver)
     * instead of always using the provider's general-purpose default.
     *
     * @return array<string, mixed>
     */
    public function generateStructured(string $prompt, ?string $systemInstruction = null, ?AiTask $task = null): array;

    /**
     * Sends an image alongside a text prompt and returns the response
     * decoded as a structured array. $task lets the driver pick a model
     * suited to the workload (see AiModelResolver) instead of always using
     * the provider's general-purpose default.
     *
     * @return array<string, mixed>
     */
    public function analyzeImage(UploadedFile $image, string $prompt, ?string $systemInstruction = null, ?AiTask $task = null): array;
}
