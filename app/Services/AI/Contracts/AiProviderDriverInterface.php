<?php

namespace App\Services\AI\Contracts;

use App\Enums\AiTask;
use App\Services\AI\Support\AiUsage;

/**
 * What a concrete provider driver (OpenAI, Gemini, Claude, Groq) exposes on
 * top of the plain AiDriverInterface, so AiManager can log usage per call
 * and `ai:doctor` can describe each provider — without either of them
 * knowing any vendor-specific detail. AiManager itself only implements
 * AiDriverInterface, since it is a router, not a provider.
 */
interface AiProviderDriverInterface extends AiDriverInterface
{
    /**
     * The model this driver uses for the given task (see AiModelResolver).
     */
    public function modelFor(?AiTask $task): string;

    /**
     * Whether an API key is configured (never exposes its value).
     */
    public function hasApiKey(): bool;

    /**
     * Name of the environment variable that holds this driver's API key.
     */
    public function apiKeyEnvName(): string;

    /**
     * Token usage reported by the provider for the most recent call made
     * through this driver, or null when the call failed or the API didn't
     * report it.
     */
    public function lastUsage(): ?AiUsage;
}
