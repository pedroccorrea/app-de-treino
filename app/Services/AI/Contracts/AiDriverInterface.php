<?php

namespace App\Services\AI\Contracts;

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
     * fences around the JSON payload before decoding it.
     *
     * @return array<string, mixed>
     */
    public function generateStructured(string $prompt, ?string $systemInstruction = null): array;

    /**
     * Sends an image alongside a text prompt and returns the response
     * decoded as a structured array.
     *
     * @return array<string, mixed>
     */
    public function analyzeImage(UploadedFile $image, string $prompt, ?string $systemInstruction = null): array;
}
