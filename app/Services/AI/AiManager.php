<?php

namespace App\Services\AI;

use App\Enums\AiFeature;
use App\Enums\AiTask;
use App\Exceptions\AiException;
use App\Exceptions\AiProviderException;
use App\Exceptions\AiUnavailableException;
use App\Exceptions\AiUnreadableResponseException;
use App\Services\AI\Contracts\AiDriverInterface;
use App\Services\AI\Contracts\AiProviderDriverInterface;
use App\Services\AI\Drivers\ClaudeDriver;
use App\Services\AI\Drivers\GeminiDriver;
use App\Services\AI\Drivers\GroqDriver;
use App\Services\AI\Drivers\OpenAiDriver;
use App\Services\AI\Support\AiSecretRedactor;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Manager;
use Throwable;

/**
 * Resolves the configured AI provider driver (OpenAI, Gemini, Claude, Groq)
 * and is the only point of contact domain services should have with AI
 * providers. Every operation is attempted on the primary driver
 * (AI_DEFAULT_DRIVER, OpenAI by default) first; if it throws for any reason
 * (no credit, bad key, outage, timeout, empty/invalid JSON), AiManager
 * retries the same operation on the reserve driver (AI_FALLBACK_DRIVER,
 * Gemini by default) before degrading, so a single provider outage never has
 * to be handled by each domain service individually.
 *
 * The optional AiTask is forwarded to whichever driver serves the request,
 * so each provider picks a model suited to the workload (see
 * AiModelResolver) even across failover. The optional AiFeature only labels
 * the usage log written for every attempt.
 */
class AiManager extends Manager implements AiDriverInterface
{
    private const USER_MESSAGE = 'Não foi possível concluir a solicitação de IA no momento.';

    public function getDefaultDriver(): string
    {
        return config('services.ai.default_driver') ?: 'openai';
    }

    protected function getFallbackDriver(): ?string
    {
        return config('services.ai.fallback_driver') ?: null;
    }

    protected function createOpenaiDriver(): OpenAiDriver
    {
        return $this->container->make(OpenAiDriver::class);
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

    public function generateStructured(string $prompt, ?string $systemInstruction = null, ?AiTask $task = null, ?AiFeature $feature = null): array
    {
        return $this->withFailover(
            fn (AiDriverInterface $driver) => $driver->generateStructured($prompt, $systemInstruction, $task),
            $task,
            $feature,
        );
    }

    public function analyzeImage(UploadedFile $image, string $prompt, ?string $systemInstruction = null, ?AiTask $task = null, ?AiFeature $feature = null): array
    {
        return $this->withFailover(
            fn (AiDriverInterface $driver) => $driver->analyzeImage($image, $prompt, $systemInstruction, $task),
            $task,
            $feature,
        );
    }

    /**
     * Sends a minimal FastText JSON request to ONE driver, without failover,
     * so `ai:doctor` can check each configured provider (SSL, key, model)
     * individually. The call is usage-logged like any other.
     *
     * @param  string  $role  "principal" or "reserva"
     * @return array<string, mixed>
     */
    public function probe(string $name, string $role): array
    {
        $driver = $this->driver($name);

        try {
            $result = $driver->generateStructured('Responda apenas com o JSON {"status": "OK"}.', task: AiTask::FastText);
        } catch (Throwable $e) {
            $this->logUsage($name, $driver, AiTask::FastText, AiFeature::Diagnostic, $role, 'falha');

            throw $e;
        }

        $this->logUsage($name, $driver, AiTask::FastText, AiFeature::Diagnostic, $role, 'sucesso');

        return $result;
    }

    /**
     * Ordered list of drivers to try: the primary, then the reserve. Groq's
     * vision model isn't accurate enough to read workout-sheet photos, so it
     * is removed from Vision tasks in either position, no matter how
     * AI_DEFAULT_DRIVER / AI_FALLBACK_DRIVER are (mis)configured.
     *
     * @return array<string, string> driver name => role ("principal"/"reserva")
     */
    private function attemptPlan(?AiTask $task): array
    {
        $plan = [];

        foreach (['principal' => $this->getDefaultDriver(), 'reserva' => $this->getFallbackDriver()] as $role => $name) {
            if ($name === null || isset($plan[$name])) {
                continue;
            }

            if ($task === AiTask::Vision && $name === 'groq') {
                continue;
            }

            $plan[$name] = $role;
        }

        return $plan;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws AiException
     */
    private function withFailover(Closure $operation, ?AiTask $task, ?AiFeature $feature): array
    {
        $failures = [];

        foreach ($this->attemptPlan($task) as $name => $role) {
            $driver = null;

            try {
                $driver = $this->driver($name);
                $result = $operation($driver);
                $this->logUsage($name, $driver, $task, $feature, $role, 'sucesso');

                return $result;
            } catch (Throwable $e) {
                $failures[] = $e;
                $this->logUsage($name, $driver, $task, $feature, $role, 'falha');

                Log::warning($role === 'principal'
                    ? "Driver de IA principal [{$name}] falhou. Acionando a reserva."
                    : "Driver de IA reserva [{$name}] também falhou. Degradando.", [
                        'message' => AiSecretRedactor::redact($e->getMessage()),
                    ]);
            }
        }

        throw $this->finalException($failures);
    }

    /**
     * Tells callers WHY everything failed: if any provider actually answered
     * but its content couldn't be used, it's an unreadable response (e.g. a
     * photo that can't be read); otherwise no provider answered at all and
     * the AI service is simply unavailable right now.
     *
     * @param  array<int, Throwable>  $failures
     */
    private function finalException(array $failures): AiException
    {
        $last = end($failures) ?: null;

        $answeredButUnreadable = collect($failures)->contains(
            fn (Throwable $e) => $e instanceof AiProviderException && $e->isUnreadableResponse()
        );

        return $answeredButUnreadable
            ? new AiUnreadableResponseException(self::USER_MESSAGE, previous: $last)
            : new AiUnavailableException(self::USER_MESSAGE, previous: $last);
    }

    /**
     * One log line per AI call: provider, model, feature, task, role
     * (principal/reserva), outcome and tokens when the API reported them.
     * Never the key, the image or the prompt.
     */
    private function logUsage(string $name, ?AiDriverInterface $driver, ?AiTask $task, ?AiFeature $feature, string $role, string $outcome): void
    {
        $isProvider = $driver instanceof AiProviderDriverInterface;
        $usage = $isProvider && $outcome === 'sucesso' ? $driver->lastUsage() : null;

        Log::info('Uso de IA', [
            'provider' => $name,
            'model' => $isProvider ? $driver->modelFor($task) : null,
            'feature' => $feature?->value,
            'task' => $task?->value,
            'role' => $role,
            'outcome' => $outcome,
            'input_tokens' => $usage?->inputTokens,
            'output_tokens' => $usage?->outputTokens,
            'total_tokens' => $usage?->totalTokens,
        ]);
    }
}
