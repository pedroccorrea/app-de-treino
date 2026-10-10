<?php

namespace App\Services\AI\Drivers;

use App\Enums\AiTask;
use App\Services\AI\Contracts\AiProviderDriverInterface;
use App\Services\AI\GeminiClient;
use App\Services\AI\Support\AiUsage;
use Illuminate\Http\UploadedFile;

/**
 * Adapts GeminiClient (the low-level Gemini HTTP client) to the driver
 * contract, so AiManager can address it interchangeably with any other
 * provider driver.
 */
class GeminiDriver implements AiProviderDriverInterface
{
    public function __construct(private readonly GeminiClient $client) {}

    public function generateStructured(string $prompt, ?string $systemInstruction = null, ?AiTask $task = null): array
    {
        return $this->client->generate($this->withSystemInstruction($prompt, $systemInstruction), task: $task);
    }

    public function analyzeImage(UploadedFile $image, string $prompt, ?string $systemInstruction = null, ?AiTask $task = null): array
    {
        return $this->client->generate(
            $this->withSystemInstruction($prompt, $systemInstruction),
            [
                'mimeType' => $image->getMimeType() ?: 'image/jpeg',
                'data' => base64_encode(file_get_contents($image->getRealPath())),
            ],
            task: $task,
        );
    }

    public function modelFor(?AiTask $task): string
    {
        return $this->client->modelFor($task);
    }

    public function hasApiKey(): bool
    {
        return $this->client->hasApiKey();
    }

    public function apiKeyEnvName(): string
    {
        return 'GEMINI_API_KEY';
    }

    public function lastUsage(): ?AiUsage
    {
        return $this->client->lastUsage();
    }

    private function withSystemInstruction(string $prompt, ?string $systemInstruction): string
    {
        return $systemInstruction === null ? $prompt : "{$systemInstruction}\n\n{$prompt}";
    }
}
