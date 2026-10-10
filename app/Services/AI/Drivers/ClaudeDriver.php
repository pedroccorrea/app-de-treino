<?php

namespace App\Services\AI\Drivers;

use App\Enums\AiTask;
use App\Exceptions\ClaudeException;
use App\Services\AI\Contracts\AiProviderDriverInterface;
use App\Services\AI\Support\AiJsonSanitizer;
use App\Services\AI\Support\AiModelResolver;
use App\Services\AI\Support\AiSecretRedactor;
use App\Services\AI\Support\AiTimeoutResolver;
use App\Services\AI\Support\AiUsage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Encapsulates the connection to the Anthropic Messages API. Optional driver,
 * selectable via AI_DEFAULT_DRIVER=claude or AI_FALLBACK_DRIVER=claude.
 */
class ClaudeDriver implements AiProviderDriverInterface
{
    public const DEFAULT_MODEL = 'claude-opus-5';

    private ?AiUsage $lastUsage = null;

    public function generateStructured(string $prompt, ?string $systemInstruction = null, ?AiTask $task = null): array
    {
        return $this->decodeJson($this->request($prompt, $systemInstruction, task: $task));
    }

    public function analyzeImage(UploadedFile $image, string $prompt, ?string $systemInstruction = null, ?AiTask $task = null): array
    {
        return $this->decodeJson($this->request($prompt, $systemInstruction, $image, $task));
    }

    public function modelFor(?AiTask $task): string
    {
        return AiModelResolver::resolve('claude', $task, self::DEFAULT_MODEL);
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey() !== '';
    }

    public function apiKeyEnvName(): string
    {
        return 'ANTHROPIC_API_KEY';
    }

    public function lastUsage(): ?AiUsage
    {
        return $this->lastUsage;
    }

    /**
     * @throws ClaudeException
     */
    private function request(string $prompt, ?string $systemInstruction, ?UploadedFile $image = null, ?AiTask $task = null): string
    {
        $this->lastUsage = null;
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
            'model' => $this->modelFor($task),
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
                ->timeout(AiTimeoutResolver::resolve($task))
                ->post('https://api.anthropic.com/v1/messages', $payload);
        } catch (Throwable $e) {
            Log::warning('Falha de comunicação com a API da Anthropic (Claude).', ['message' => AiSecretRedactor::redact($e->getMessage())]);

            throw new ClaudeException('Não foi possível comunicar com a IA no momento. Tente novamente em instantes.');
        }

        if ($response->failed()) {
            $body = AiSecretRedactor::redact($response->body());

            Log::warning('A API da Anthropic retornou um erro.', [
                'status' => $response->status(),
                'body' => $body,
            ]);

            throw new ClaudeException('Erro retornado pela Anthropic ('.$response->status().'): '.$body);
        }

        $usage = data_get($response->json(), 'usage');
        $this->lastUsage = is_array($usage)
            ? AiUsage::fromCounts($usage['input_tokens'] ?? null, $usage['output_tokens'] ?? null)
            : null;

        $rawText = data_get($response->json(), 'content.0.text');

        if (! is_string($rawText) || trim($rawText) === '') {
            Log::warning('A API da Anthropic não retornou nenhum conteúdo de texto.');

            throw ClaudeException::unreadableResponse('A IA não retornou nenhum conteúdo.');
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

            throw ClaudeException::unreadableResponse('Não foi possível interpretar a resposta da IA.');
        }

        return $parsed;
    }

    private function apiKey(): string
    {
        return (string) config('services.claude.key');
    }
}
