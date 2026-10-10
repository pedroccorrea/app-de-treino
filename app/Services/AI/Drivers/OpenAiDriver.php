<?php

namespace App\Services\AI\Drivers;

use App\Enums\AiTask;
use App\Exceptions\OpenAiException;
use App\Services\AI\Contracts\AiProviderDriverInterface;
use App\Services\AI\Support\AiJsonSanitizer;
use App\Services\AI\Support\AiModelResolver;
use App\Services\AI\Support\AiSecretRedactor;
use App\Services\AI\Support\AiTimeoutResolver;
use App\Services\AI\Support\AiUsage;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Encapsulates the connection to the OpenAI Responses API (POST /v1/responses),
 * the primary driver behind AiManager (AI_DEFAULT_DRIVER=openai). Images are
 * sent as `input_image` data URLs and the response is requested in native
 * JSON mode (`text.format = json_object`); the result still goes through
 * AiJsonSanitizer and every domain service's own PHP validation.
 *
 * Retry policy: credit / spend-limit errors are never retried (retrying can't
 * restore access, so AiManager goes straight to the reserve). Transient
 * errors (5xx, plain rate limit) get at most ONE immediate retry, and only
 * within what's left of the task's timeout budget, so the whole operation
 * never takes longer than AiTimeoutResolver allows.
 */
class OpenAiDriver implements AiProviderDriverInterface
{
    public const DEFAULT_MODEL = 'gpt-6-luna';

    private const ENDPOINT = 'https://api.openai.com/v1/responses';

    /**
     * Error codes OpenAI documents for exhausted credit, spend limits and
     * inactive billing (see "Error codes" in the OpenAI API docs). Retrying
     * any of these is pointless until the account is fixed.
     */
    private const BILLING_ERROR_CODES = [
        'insufficient_quota',
        'credit_balance_exhausted',
        'organization_spend_limit_exceeded',
        'project_spend_limit_exceeded',
        'organization_usage_limit_exceeded',
        'billing_hard_limit_reached',
        'billing_not_active',
    ];

    private const MAX_ATTEMPTS = 2;

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
        return AiModelResolver::resolve('openai', $task, self::DEFAULT_MODEL);
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey() !== '';
    }

    public function apiKeyEnvName(): string
    {
        return 'OPENAI_API_KEY';
    }

    public function lastUsage(): ?AiUsage
    {
        return $this->lastUsage;
    }

    /**
     * @throws OpenAiException
     */
    private function request(string $prompt, ?string $systemInstruction, ?UploadedFile $image = null, ?AiTask $task = null): string
    {
        $this->lastUsage = null;

        if (! $this->hasApiKey()) {
            throw new OpenAiException('A chave OPENAI_API_KEY está vazia! Verifique seu arquivo .env e execute php artisan config:clear.');
        }

        $payload = $this->payload($prompt, $systemInstruction, $image, $task);
        $budget = AiTimeoutResolver::resolve($task);
        $startedAt = microtime(true);

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $remaining = (int) floor($budget - (microtime(true) - $startedAt));

            try {
                $response = Http::withToken($this->apiKey())
                    ->acceptJson()
                    ->timeout(max(1, $remaining))
                    ->post(self::ENDPOINT, $payload);
            } catch (Throwable $e) {
                // Connection failures/timeouts are not retried here: the
                // budget is usually already spent, and the reserve provider
                // is a better second attempt than the same one.
                Log::warning('Falha de comunicação com a API da OpenAI.', ['message' => AiSecretRedactor::redact($e->getMessage())]);

                throw new OpenAiException('Não foi possível comunicar com a IA no momento. Tente novamente em instantes.');
            }

            if ($response->successful()) {
                return $this->extractText($response);
            }

            $exception = $this->failureException($response);

            $remainingAfter = $budget - (microtime(true) - $startedAt);

            if ($exception->isBillingError() || ! $this->isTransient($response) || $attempt === self::MAX_ATTEMPTS || $remainingAfter < 1) {
                throw $exception;
            }
        }

        throw new OpenAiException('Não foi possível comunicar com a IA no momento. Tente novamente em instantes.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $prompt, ?string $systemInstruction, ?UploadedFile $image, ?AiTask $task): array
    {
        $content = [['type' => 'input_text', 'text' => $prompt]];

        if ($image !== null) {
            $mimeType = $image->getMimeType() ?: 'image/jpeg';
            $data = base64_encode(file_get_contents($image->getRealPath()));

            $content[] = [
                'type' => 'input_image',
                'image_url' => "data:{$mimeType};base64,{$data}",
                // Handwritten/thermal-printed sheets need full resolution.
                'detail' => 'high',
            ];
        }

        $payload = [
            'model' => $this->modelFor($task),
            'input' => [
                ['role' => 'user', 'content' => $content],
            ],
            'text' => ['format' => ['type' => 'json_object']],
            'reasoning' => ['effort' => $this->reasoningEffort($task)],
            // Nothing here needs to live on OpenAI's side after the answer.
            'store' => false,
        ];

        if ($systemInstruction !== null) {
            $payload['instructions'] = $systemInstruction;
        }

        return $payload;
    }

    /**
     * FastText answers back screens the user is waiting on (6s budget), so
     * they run without extended reasoning; Vision has room for a bit more.
     */
    private function reasoningEffort(?AiTask $task): string
    {
        return (string) config(
            'services.openai.reasoning_effort.'.($task ?? AiTask::Vision)->value,
            $task === AiTask::FastText ? 'none' : 'low',
        );
    }

    /**
     * @throws OpenAiException
     */
    private function extractText(Response $response): string
    {
        $body = $response->json();

        $usage = data_get($body, 'usage');
        $this->lastUsage = is_array($usage)
            ? AiUsage::fromCounts($usage['input_tokens'] ?? null, $usage['output_tokens'] ?? null, $usage['total_tokens'] ?? null)
            : null;

        $text = '';

        foreach ((array) data_get($body, 'output', []) as $item) {
            if (($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ((array) ($item['content'] ?? []) as $part) {
                if (($part['type'] ?? null) === 'output_text' && is_string($part['text'] ?? null)) {
                    $text .= $part['text'];
                }
            }
        }

        if (trim($text) === '') {
            Log::warning('A API da OpenAI não retornou nenhum conteúdo de texto.', [
                'status' => data_get($body, 'status'),
                'incomplete_reason' => data_get($body, 'incomplete_details.reason'),
            ]);

            throw OpenAiException::unreadableResponse('A IA não retornou nenhum conteúdo.');
        }

        return $text;
    }

    private function failureException(Response $response): OpenAiException
    {
        $code = (string) data_get($response->json(), 'error.code', '');
        $type = (string) data_get($response->json(), 'error.type', '');
        $isBilling = in_array($code, self::BILLING_ERROR_CODES, true) || in_array($type, self::BILLING_ERROR_CODES, true);

        Log::warning('A API da OpenAI retornou um erro.', [
            'status' => $response->status(),
            'code' => $code ?: null,
            'type' => $type ?: null,
            'body' => AiSecretRedactor::redact($response->body()),
        ]);

        $message = 'Erro retornado pela OpenAI ('.$response->status().($code !== '' ? ", {$code}" : '').').';

        return $isBilling ? OpenAiException::billing($message) : new OpenAiException($message);
    }

    private function isTransient(Response $response): bool
    {
        return $response->status() === 429 || $response->serverError();
    }

    /**
     * @return array<string, mixed>
     *
     * @throws OpenAiException
     */
    private function decodeJson(string $rawText): array
    {
        $parsed = AiJsonSanitizer::decode($rawText);

        if ($parsed === null) {
            Log::warning('Não foi possível interpretar a resposta JSON da IA (OpenAI).', ['raw_length' => strlen($rawText)]);

            throw OpenAiException::unreadableResponse('Não foi possível interpretar a resposta da IA.');
        }

        return $parsed;
    }

    private function apiKey(): string
    {
        return (string) config('services.openai.key');
    }
}
