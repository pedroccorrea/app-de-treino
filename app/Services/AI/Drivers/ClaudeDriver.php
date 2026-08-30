<?php

namespace App\Services\AI\Drivers;

use App\Exceptions\ClaudeException;
use App\Services\AI\Contracts\AiDriverInterface;
use App\Services\AI\Support\AiJsonSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Encapsulates the connection to the Anthropic Messages API. Used as the
 * secondary/failover driver behind AiManager, and directly selectable via
 * AI_DEFAULT_DRIVER=claude.
 */
class ClaudeDriver implements AiDriverInterface
{
    public function generateStructured(string $prompt, ?string $systemInstruction = null): array
    {
        return $this->decodeJson($this->request($prompt, $systemInstruction));
    }

    public function analyzeImage(UploadedFile $image, string $prompt, ?string $systemInstruction = null): array
    {
        return $this->decodeJson($this->request($prompt, $systemInstruction, $image));
    }

    /**
     * @throws ClaudeException
     */
    private function request(string $prompt, ?string $systemInstruction, ?UploadedFile $image = null): string
    {
        $apiKey = $this->apiKey();

        if (empty($apiKey)) {
            throw new ClaudeException('A chave ANTHROPIC_API_KEY está vazia! Verifique seu arquivo .env e execute php artisan config:clear.');
        }

        $content = [];

        if ($image !== null) {
            $content[] = [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $image->getMimeType() ?: 'image/jpeg',
                    'data' => base64_encode(file_get_contents($image->getRealPath())),
                ],
            ];
        }

        $content[] = ['type' => 'text', 'text' => $prompt];

        $payload = [
            'model' => $this->model(),
            'max_tokens' => 4096,
            'messages' => [
                ['role' => 'user', 'content' => $content],
            ],
        ];

        if ($systemInstruction !== null) {
            $payload['system'] = $systemInstruction;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])
                ->timeout(90)
                ->post('https://api.anthropic.com/v1/messages', $payload);
        } catch (Throwable $e) {
            Log::warning('Falha de comunicação com a API da Anthropic (Claude).', ['message' => $e->getMessage()]);

            throw new ClaudeException('Não foi possível comunicar com a IA no momento. Tente novamente em instantes.', previous: $e);
        }

        if ($response->failed()) {
            Log::warning('A API da Anthropic retornou um erro.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new ClaudeException('Erro retornado pela Anthropic ('.$response->status().'): '.$response->body());
        }

        $rawText = data_get($response->json(), 'content.0.text');

        if (! is_string($rawText) || trim($rawText) === '') {
            Log::warning('A API da Anthropic não retornou nenhum conteúdo de texto.');

            throw new ClaudeException('A IA não retornou nenhum conteúdo.');
        }

        return $rawText;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ClaudeException
     */
    private function decodeJson(string $rawText): array
    {
        $parsed = AiJsonSanitizer::decode($rawText);

        if ($parsed === null) {
            Log::warning('Não foi possível interpretar a resposta JSON da IA (Claude).', ['raw_text' => $rawText]);

            throw new ClaudeException('Não foi possível interpretar a resposta da IA.');
        }

        return $parsed;
    }

    private function apiKey(): string
    {
        return (string) config('services.claude.key');
    }

    private function model(): string
    {
        return (string) (config('services.claude.model') ?: 'claude-opus-5');
    }
}
