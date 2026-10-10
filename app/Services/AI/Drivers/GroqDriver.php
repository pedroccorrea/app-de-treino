<?php

namespace App\Services\AI\Drivers;

use App\Enums\AiTask;
use App\Exceptions\GroqException;
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
 * Encapsulates the connection to the Groq API (endpoint compatible with the
 * OpenAI chat-completions format), used as an optional ultra-low-latency
 * driver for AiTask::FastText workloads. Selectable via
 * AI_DEFAULT_DRIVER=groq or AI_FALLBACK_DRIVER=groq — but never for Vision
 * (AiManager keeps it out of the workout-sheet scan).
 */
class GroqDriver implements AiProviderDriverInterface
{
    public const DEFAULT_MODEL = 'openai/gpt-oss-20b';

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
        return AiModelResolver::resolve('groq', $task, self::DEFAULT_MODEL);
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey() !== '';
    }

    public function apiKeyEnvName(): string
    {
        return 'GROQ_API_KEY';
    }

    public function lastUsage(): ?AiUsage
    {
        return $this->lastUsage;
    }

    /**
     * @throws GroqException
     */
    private function request(string $prompt, ?string $systemInstruction, ?UploadedFile $image = null, ?AiTask $task = null): string
    {
        $this->lastUsage = null;
        $apiKey = $this->apiKey();

        if (empty($apiKey)) {
            throw new GroqException('A chave GROQ_API_KEY está vazia! Verifique seu arquivo .env e execute php artisan config:clear.');
        }

        $messages = [];

        if ($systemInstruction !== null) {
            $messages[] = ['role' => 'system', 'content' => $systemInstruction];
        }

        if ($image !== null) {
            $mimeType = $image->getMimeType() ?: 'image/jpeg';
            $data = base64_encode(file_get_contents($image->getRealPath()));

            $messages[] = [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $prompt],
                    ['type' => 'image_url', 'image_url' => ['url' => "data:{$mimeType};base64,{$data}"]],
                ],
            ];
        } else {
            $messages[] = ['role' => 'user', 'content' => $prompt];
        }

        $payload = [
            'model' => $this->modelFor($task),
            'messages' => $messages,
        ];

        try {
            $response = Http::withToken($apiKey)
                ->timeout(AiTimeoutResolver::resolve($task))
                ->post('https://api.groq.com/openai/v1/chat/completions', $payload);
        } catch (Throwable $e) {
            Log::warning('Falha de comunicação com a API da Groq.', ['message' => AiSecretRedactor::redact($e->getMessage())]);

            throw new GroqException('Não foi possível comunicar com a IA no momento. Tente novamente em instantes.');
        }

        if ($response->failed()) {
            $body = AiSecretRedactor::redact($response->body());

            Log::warning('A API da Groq retornou um erro.', [
                'status' => $response->status(),
                'body' => $body,
            ]);

            throw new GroqException('Erro retornado pela Groq ('.$response->status().'): '.$body);
        }

        $usage = data_get($response->json(), 'usage');
        $this->lastUsage = is_array($usage)
            ? AiUsage::fromCounts($usage['prompt_tokens'] ?? null, $usage['completion_tokens'] ?? null, $usage['total_tokens'] ?? null)
            : null;

        $rawText = data_get($response->json(), 'choices.0.message.content');

        if (! is_string($rawText) || trim($rawText) === '') {
            Log::warning('A API da Groq não retornou nenhum conteúdo de texto.');

            throw GroqException::unreadableResponse('A IA não retornou nenhum conteúdo.');
        }

        return $rawText;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws GroqException
     */
    private function decodeJson(string $rawText): array
    {
        $parsed = AiJsonSanitizer::decode($rawText);

        if ($parsed === null) {
            Log::warning('Não foi possível interpretar a resposta JSON da IA (Groq).', ['raw_text' => $rawText]);

            throw GroqException::unreadableResponse('Não foi possível interpretar a resposta da IA.');
        }

        return $parsed;
    }

    private function apiKey(): string
    {
        return (string) config('services.groq.key');
    }
}
