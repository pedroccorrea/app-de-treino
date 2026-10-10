<?php

namespace App\Services\AI\Support;

/**
 * Strips API keys out of any text before it reaches a log, an exception
 * message or the console. Providers sometimes echo (part of) the key back in
 * error bodies (e.g. OpenAI's "Incorrect API key provided: sk-...") and cURL
 * errors may include the request URL, so every driver runs error text through
 * here instead of trusting the provider not to leak it.
 */
class AiSecretRedactor
{
    private const PLACEHOLDER = '[CHAVE OCULTA]';

    /**
     * Shapes of known provider keys, so even a partially-masked key echoed
     * back by the API (or a key not present in the current config) is hidden.
     */
    private const KEY_PATTERNS = [
        '/sk-[A-Za-z0-9_\-*.]{6,}/',   // OpenAI / Anthropic (sk-ant-...)
        '/AIza[0-9A-Za-z_\-]{10,}/',    // Google (Gemini)
        '/gsk_[A-Za-z0-9]{10,}/',       // Groq
    ];

    public static function redact(string $text): string
    {
        foreach (self::configuredKeys() as $key) {
            $text = str_replace($key, self::PLACEHOLDER, $text);
        }

        return (string) preg_replace(self::KEY_PATTERNS, self::PLACEHOLDER, $text);
    }

    /**
     * @return array<int, string>
     */
    private static function configuredKeys(): array
    {
        return array_values(array_filter(
            array_map(
                fn (string $provider) => (string) config("services.{$provider}.key"),
                ['openai', 'gemini', 'claude', 'groq'],
            ),
            fn (string $key) => strlen($key) >= 8,
        ));
    }
}
