<?php

namespace App\Services\AI;

use App\Enums\AiTask;
use App\Exceptions\GeminiException;
use App\Services\AI\Support\AiJsonSanitizer;
use App\Services\AI\Support\AiModelResolver;
use App\Services\AI\Support\AiSecretRedactor;
use App\Services\AI\Support\AiTimeoutResolver;
use App\Services\AI\Support\AiUsage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Single point of contact with the Google Gemini API (generateContent),
 * wrapped by GeminiDriver. Targets Gemini 3.8 Flash on the free tier: the
 * key travels only in the `x-goog-api-key` header (never in the URL), no
 * sampling parameters are sent (temperature/top_p/top_k were removed for
 * Gemini 3.8), reasoning is controlled by `thinkingLevel` per AiTask and the
 * answer is requested as native JSON (`responseMimeType`), still going
 * through AiJsonSanitizer afterwards.
 */
class GeminiClient
{
    public const DEFAULT_MODEL = 'gemini-3.8-flash';

    private ?AiUsage $lastUsage = null;

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
    public function generate(string $prompt, ?array $inlineImage = null, ?AiTask $task = null): array
    {
        return $this->decodeJson($this->request($prompt, $inlineImage, $task));
    }

    public function modelFor(?AiTask $task): string
    {
        return AiModelResolver::resolve('gemini', $task, self::DEFAULT_MODEL);
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey() !== '';
    }

    public function lastUsage(): ?AiUsage
    {
        return $this->lastUsage;
    }

    /**
     * @param  array{mimeType: string, data: string}|null  $inlineImage
     *
     * @throws GeminiException
     */
    private function request(string $prompt, ?array $inlineImage = null, ?AiTask $task = null): string
    {
        $this->lastUsage = null;
        $apiKey = $this->apiKey();

        if (empty($apiKey)) {
            throw new GeminiException('A chave GEMINI_API_KEY está vazia! Verifique seu arquivo .env e execute php artisan config:clear.');
        }

        $parts = [['text' => $prompt]];

        if ($inlineImage !== null) {
            $parts[] = ['inlineData' => $inlineImage];
        }

        $maxTries = 3;
        $response = null;

        for ($attempt = 1; $attempt <= $maxTries; $attempt++) {
            try {
                $response = Http::withHeaders(['x-goog-api-key' => $apiKey])
                    ->timeout(AiTimeoutResolver::resolve($task))
                    ->post(
                        "https://generativelanguage.googleapis.com/v1beta/models/{$this->modelFor($task)}:generateContent",
                        [
                            'contents' => [
                                ['parts' => $parts],
                            ],
                            'generationConfig' => [
                                'responseMimeType' => 'application/json',
                                'thinkingConfig' => ['thinkingLevel' => $this->thinkingLevel($task)],
                            ],
                        ]
                    );

                // Se a API estiver sobrecarregada, esperamos 1 segundo e tentamos de novo.
                if ($response->status() === 503 && $attempt < $maxTries) {
                    sleep(1);

                    continue;
                }

                break;
            } catch (Throwable $e) {
                if ($attempt < $maxTries) {
                    sleep(1);

                    continue;
                }

                Log::warning('Falha de comunicação com a API do Gemini.', ['message' => AiSecretRedactor::redact($e->getMessage())]);
                throw new GeminiException('Não foi possível comunicar com a IA no momento. Tente novamente em instantes.');
            }
        }

        if ($response->failed()) {
            $body = AiSecretRedactor::redact($response->body());

            Log::warning('A API do Gemini retornou um erro.', [
                'status' => $response->status(),
                'body' => $body,
            ]);

            throw new GeminiException('Erro retornado pelo Google Gemini ('.$response->status().'): '.$body);
        }

        $usage = data_get($response->json(), 'usageMetadata');
        $this->lastUsage = is_array($usage)
            ? AiUsage::fromCounts($usage['promptTokenCount'] ?? null, $usage['candidatesTokenCount'] ?? null, $usage['totalTokenCount'] ?? null)
            : null;

        $rawText = $this->answerText($response->json());

        if (trim($rawText) === '') {
            Log::warning('A API do Gemini não retornou nenhum conteúdo de texto.');

            throw GeminiException::unreadableResponse('A IA não retornou nenhum conteúdo.');
        }

        return $rawText;
    }

    /**
     * Joins the answer's text parts, skipping "thought" parts that thinking
     * models may include before the actual answer.
     *
     * @param  array<string, mixed>|null  $body
     */
    private function answerText(?array $body): string
    {
        $text = '';

        foreach ((array) data_get($body, 'candidates.0.content.parts', []) as $part) {
            if (! ($part['thought'] ?? false) && is_string($part['text'] ?? null)) {
                $text .= $part['text'];
            }
        }

        return $text;
    }

    /**
     * Gemini 3.8 Flash accepts low/medium/high (not "minimal"): FastText uses
     * the lowest level so answers fit its short timeout; Vision keeps the
     * model's default ("medium") for reading photos.
     */
    private function thinkingLevel(?AiTask $task): string
    {
        return (string) config(
            'services.gemini.thinking_level.'.($task ?? AiTask::Vision)->value,
            $task === AiTask::FastText ? 'low' : 'medium',
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws GeminiException
     */
    private function decodeJson(string $rawText): array
    {
        $parsed = AiJsonSanitizer::decode($rawText);

        if ($parsed === null) {
            Log::warning('Não foi possível interpretar a resposta JSON da IA.', ['raw_text' => $rawText]);

            throw GeminiException::unreadableResponse('Não foi possível interpretar a resposta da IA.');
        }

        return $parsed;
    }

    private function apiKey(): string
    {
        return (string) (config('services.gemini.key') ?: env('GEMINI_API_KEY'));
    }
}
