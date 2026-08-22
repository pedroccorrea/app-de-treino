<?php

namespace App\Services\AI;

use App\Exceptions\GeminiException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Single point of contact with the Google Gemini API. Every AI-backed
 * service (workout scanning, progressive overload, dashboard analytics)
 * goes through this client instead of calling Http::* directly, so the
 * request setup, JSON sanitization and error handling only live in one
 * place.
 */
class GeminiClient
{
    public function __construct()
    {
        // Large workout-sheet photos are base64-encoded in memory before
        // being sent to the API; the default PHP limit isn't enough on
        // some hosts.
        ini_set('memory_limit', '512M');
    }

    /**
     * Sends a text prompt (optionally with an inline image) to Gemini and
     * returns the response decoded as an associative array. Markdown code
     * fences around the JSON payload are stripped automatically, since the
     * model frequently wraps its answer in ```json ... ```.
     *
     * @param  array{mimeType: string, data: string}|null  $inlineImage
     * @return array<string, mixed>
     *
     * @throws GeminiException
     */
    public function generate(string $prompt, ?array $inlineImage = null): array
    {
        return $this->decodeJson($this->request($prompt, $inlineImage));
    }

    /**
     * Sends a trivial one-word prompt and returns Gemini's raw text answer,
     * without requiring it to be JSON. Used by `ai:doctor` to validate SSL
     * and API-key authentication without depending on prompt-engineering.
     *
     * @throws GeminiException
     */
    public function ping(): string
    {
        return trim($this->request('Responda apenas com a palavra: OK'));
    }

    /**
     * @param  array{mimeType: string, data: string}|null  $inlineImage
     *
     * @throws GeminiException
     */
    private function request(string $prompt, ?array $inlineImage = null): string
    {
        $apiKey = $this->apiKey();

        if (empty($apiKey)) {
            throw new GeminiException('A chave GEMINI_API_KEY está vazia! Verifique seu arquivo .env e execute php artisan config:clear.');
        }

        $parts = [['text' => $prompt]];

        if ($inlineImage !== null) {
            $parts[] = ['inlineData' => $inlineImage];
        }

        try {
            $response = Http::withoutVerifying()
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->timeout(60)
                ->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/{$this->model()}:generateContent?key={$apiKey}",
                    [
                        'contents' => [
                            ['parts' => $parts],
                        ],
                    ]
                );
        } catch (Throwable $e) {
            throw new GeminiException('Não foi possível comunicar com a IA no momento. Tente novamente em instantes.', previous: $e);
        }

        if ($response->failed()) {
            throw new GeminiException('Erro retornado pelo Google Gemini ('.$response->status().'): '.$response->body());
        }

        $rawText = data_get($response->json(), 'candidates.0.content.parts.0.text');

        if (! is_string($rawText) || trim($rawText) === '') {
            throw new GeminiException('A IA não retornou nenhum conteúdo.');
        }

        return $rawText;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws GeminiException
     */
    private function decodeJson(string $rawText): array
    {
        $sanitized = preg_replace('/^```(?:json)?\s+|\s+```$/m', '', trim($rawText));
        $parsed = json_decode($sanitized, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($parsed)) {
            throw new GeminiException('Não foi possível interpretar a resposta da IA.');
        }

        return $parsed;
    }

    private function apiKey(): string
    {
        return (string) (config('services.gemini.key') ?: env('GEMINI_API_KEY'));
    }

    private function model(): string
    {
        return (string) (config('services.gemini.model') ?: env('GEMINI_MODEL', 'gemini-2.0-flash'));
    }
}
