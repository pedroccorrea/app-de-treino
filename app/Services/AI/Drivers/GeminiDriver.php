<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Contracts\AiDriverInterface;
use App\Services\AI\GeminiClient;
use Illuminate\Http\UploadedFile;

/**
 * Adapts GeminiClient (the low-level Gemini HTTP client) to the driver
 * contract, so AiManager can address it interchangeably with any other
 * provider driver.
 */
class GeminiDriver implements AiDriverInterface
{
    public function __construct(private readonly GeminiClient $client) {}

    public function generateStructured(string $prompt, ?string $systemInstruction = null): array
    {
        return $this->client->generate($this->withSystemInstruction($prompt, $systemInstruction));
    }

    public function analyzeImage(UploadedFile $image, string $prompt, ?string $systemInstruction = null): array
    {
        return $this->client->generate(
            $this->withSystemInstruction($prompt, $systemInstruction),
            [
                'mimeType' => $image->getMimeType() ?: 'image/jpeg',
                'data' => base64_encode(file_get_contents($image->getRealPath())),
            ]
        );
    }

    private function withSystemInstruction(string $prompt, ?string $systemInstruction): string
    {
        return $systemInstruction === null ? $prompt : "{$systemInstruction}\n\n{$prompt}";
    }
}
