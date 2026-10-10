<?php

namespace App\Console\Commands;

use App\Enums\AiTask;
use App\Services\AI\AiManager;
use App\Services\AI\Contracts\AiProviderDriverInterface;
use App\Services\AI\Support\AiSecretRedactor;
use Illuminate\Console\Command;
use Throwable;

/**
 * Real-environment diagnostic for the AI integration: checks the PHP memory
 * limit and, for the primary (AI_DEFAULT_DRIVER) and reserve
 * (AI_FALLBACK_DRIVER) providers, shows the configured models, whether the
 * key is set (never its value) and performs an actual authenticated request
 * (SSL + key + model). Unlike the test suite (which fakes every AI call),
 * this command hits the real network — run it whenever you suspect the local
 * or deployed environment isn't AI-ready.
 */
class AiDoctorCommand extends Command
{
    protected $signature = 'ai:doctor';

    protected $description = 'Diagnostica se o ambiente está pronto para usar a IA (empresa principal e reserva configuradas)';

    public function handle(AiManager $aiManager): int
    {
        $this->info('🩺 Diagnóstico do ambiente de IA');
        $this->newLine();

        $checks = [$this->checkMemoryLimit()];

        $primary = (string) $aiManager->getDefaultDriver();
        $fallback = config('services.ai.fallback_driver') ?: null;

        $checks[] = $this->checkProvider($aiManager, $primary, 'principal');

        if ($fallback === null || $fallback === $primary) {
            $this->line('  <fg=yellow>[AVISO]</> Nenhuma reserva configurada (AI_FALLBACK_DRIVER): se a principal falhar, a IA degrada direto.');
        } else {
            $checks[] = $this->checkProvider($aiManager, $fallback, 'reserva');
        }

        $this->newLine();

        if (in_array(false, $checks, strict: true)) {
            $this->error('❌ Foram encontrados problemas. Corrija os itens acima antes de usar as funcionalidades de IA.');

            return self::FAILURE;
        }

        $this->info('✅ Tudo certo! O ambiente está pronto para usar a IA.');

        return self::SUCCESS;
    }

    private function checkProvider(AiManager $aiManager, string $name, string $role): bool
    {
        $this->newLine();
        $this->line("  <options=bold>IA {$role}: {$name}</>");

        try {
            $driver = $aiManager->driver($name);
        } catch (Throwable) {
            $this->line("    <fg=red>[ERRO]</> Empresa de IA desconhecida: '{$name}'. Use openai, gemini, claude ou groq.");

            return false;
        }

        if (! $driver instanceof AiProviderDriverInterface) {
            $this->line("    <fg=red>[ERRO]</> O conector '{$name}' não expõe diagnóstico.");

            return false;
        }

        $this->line('    Modelo de imagem (scan): '.$driver->modelFor(AiTask::Vision));
        $this->line('    Modelo de texto rápido: '.$driver->modelFor(AiTask::FastText));

        if ($name === 'groq') {
            $this->line('    <fg=yellow>[INFO]</> A Groq nunca é usada para o scan da ficha.');
        }

        if (! $driver->hasApiKey()) {
            $this->line("    <fg=red>[ERRO]</> {$driver->apiKeyEnvName()} não está definida.");

            return false;
        }

        $this->line("    <fg=green>[OK]</> {$driver->apiKeyEnvName()} definida.");

        try {
            $aiManager->probe($name, $role);
            $this->line('    <fg=green>[OK]</> Conexão validada (HTTPS verificado + autenticação + modelo).');

            return true;
        } catch (Throwable $e) {
            $this->line('    <fg=red>[ERRO]</> Falha ao conectar: '.AiSecretRedactor::redact($e->getMessage()));

            return false;
        }
    }

    private function checkMemoryLimit(): bool
    {
        $limit = ini_get('memory_limit');
        $bytes = $this->toBytes($limit);
        $minimumBytes = 256 * 1024 * 1024;

        // -1 means "no limit", which always satisfies the minimum.
        if ($bytes !== -1 && $bytes < $minimumBytes) {
            $this->line("  <fg=red>[ERRO]</> memory_limit do PHP é {$limit}, abaixo do mínimo recomendado (256M).");

            return false;
        }

        $this->line("  <fg=green>[OK]</> memory_limit do PHP é {$limit}.");

        return true;
    }

    private function toBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '-1' || $value === '') {
            return -1;
        }

        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => (int) $value,
        };
    }
}
