<?php

namespace App\Services\AI\Support;

/**
 * Shared sanitization for LLM text responses that are supposed to be JSON.
 * LLMs frequently wrap the JSON payload in markdown code fences (```json ...
 * ```); every AI driver must strip those before decoding, per the invariant
 * in .ai/rules/ai-integration.md.
 */
class AiJsonSanitizer
{
    /**
     * @return array<string, mixed>|null null when the text isn't valid JSON
     */
    public static function decode(string $rawText): ?array
    {
        $sanitized = preg_replace('/^```(?:json)?\s+|\s+```$/m', '', trim($rawText));
        $parsed = json_decode($sanitized, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($parsed)) {
            return null;
        }

        return $parsed;
    }
}
