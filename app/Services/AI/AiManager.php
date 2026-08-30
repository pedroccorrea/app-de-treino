<?php

namespace App\Services\AI;

use App\Enums\AiTask;
use App\Exceptions\AiException;
use App\Services\AI\Contracts\AiDriverInterface;
use App\Services\AI\Drivers\ClaudeDriver;
use App\Services\AI\Drivers\GeminiDriver;
use App\Services\AI\Drivers\GroqDriver;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Manager;
use Throwable;

/**
 * Resolves the configured AI provider driver (Gemini, Claude, ...) and is
 * the only point of contact domain services should have with AI providers.
 * Every operation is attempted on the primary driver (AI_DEFAULT_DRIVER)
 * first; if it throws for any reason, AiManager automatically retries the
 * same operation on the secondary driver (AI_FALLBACK_DRIVER) before
 * degrading, so a single provider outage never has to be handled by each
 * domain service individually. The optional AiTask is forwarded to
 * whichever driver ends up serving the request, so each provider can pick
 * a model suited to the workload (see AiModelResolver) even across
 * failover, instead of always using its general-purpose default.
 */
class AiManager extends Manager implements AiDriverInterface
{
    public function getDefaultDriver(): string
    {
        return config('services.ai.default_driver', 'gemini');
    }

    protected function getFallbackDriver(): ?string
    {
        return config('services.ai.fallback_driver');
    }

    protected function createGeminiDriver(): GeminiDriver
    {
        return $this->container->make(GeminiDriver::class);
    }

    protected function createClaudeDriver(): ClaudeDriver
    {
        return $this->container->make(ClaudeDriver::class);
    }

    protected function createGroqDriver(): GroqDriver
    {
        return $this->container->make(GroqDriver::class);
    }

    public function generateStructured(string $prompt, ?string $systemInstruction = null, ?AiTask $task = null): array
    {
        return $this->withFailover(
            fn (AiDriverInterface $driver) => $driver->generateStructured($prompt, $systemInstruction, $task)
        );
    }

    public function analyzeImage(UploadedFile $image, string $prompt, ?string $systemInstruction = null, ?AiTask $task = null): array
    {
        return $this->withFailover(
            fn (AiDriverInterface $driver) => $driver->analyzeImage($image, $prompt, $systemInstruction, $task)
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws AiException
     */
    private function withFailover(Closure $operation): array
    {
        $primaryName = $this->getDefaultDriver();

        try {
            return $operation($this->driver($primaryName));
        } catch (Throwable $primaryException) {
            Log::warning("Driver de IA primário [{$primaryName}] falhou. Acionando failover.", [
                'message' => $primaryException->getMessage(),
            ]);

            $fallbackName = $this->getFallbackDriver();

            if ($fallbackName === null || $fallbackName === $primaryName) {
                throw new AiException('Não foi possível concluir a solicitação de IA no momento.', previous: $primaryException);
            }

            try {
                return $operation($this->driver($fallbackName));
            } catch (Throwable $fallbackException) {
                Log::warning("Driver de IA secundário [{$fallbackName}] também falhou. Degradando.", [
                    'message' => $fallbackException->getMessage(),
                ]);

                throw new AiException('Não foi possível concluir a solicitação de IA no momento.', previous: $fallbackException);
            }
        }
    }
}
