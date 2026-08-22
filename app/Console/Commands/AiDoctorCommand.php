<?php

namespace App\Console\Commands;

use App\Exceptions\GeminiException;
use App\Services\AI\GeminiClient;
use Illuminate\Console\Command;

/**
 * Real-environment diagnostic for the AI (Gemini) integration: checks the
 * .env key, the PHP memory limit, and performs an actual authenticated
 * ping against the Gemini API. Unlike the test suite (which fakes every
 * Gemini call), this command hits the real network — run it whenever you
 * suspect the local or deployed environment isn't AI-ready.
 */
class AiDoctorCommand extends Command
{
    protected $signature = 'ai:doctor';

    protected $description = 'Diagnostica se o ambiente está pronto para usar as funcionalidades de IA (Gemini)';

    public function handle(GeminiClient $gemini): int
    {
        $this->info('🩺 Diagnóstico do ambiente de IA (Gemini)');
        $this->newLine();

        $checks = [
            $this->checkApiKey(),
            $this->checkMemoryLimit(),
            $this->checkGeminiConnection($gemini),
        ];

        $this->newLine();

        if (in_array(false, $checks, strict: true)) {
            $this->error('❌ Foram encontrados problemas. Corrija os itens acima antes de usar as funcionalidades de IA.');

            return self::FAILURE;
        }

        $this->info('✅ Tudo certo! O ambiente está pronto para usar a IA.');

        return self::SUCCESS;
    }

    private function checkApiKey(): bool
    {
        $key = config('services.gemini.key') ?: env('GEMINI_API_KEY');

        if (empty($key)) {
            $this->line('  <fg=red>[ERRO]</> GEMINI_API_KEY não está definida no .env.');

            return false;
        }

        $masked = strlen($key) > 8
            ? substr($key, 0, 4).str_repeat('*', strlen($key) - 8).substr($key, -4)
            : str_repeat('*', strlen($key));

        $this->line("  <fg=green>[OK]</> GEMINI_API_KEY encontrada ({$masked}).");

        return true;
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

    private function checkGeminiConnection(GeminiClient $gemini): bool
    {
        try {
            $reply = $gemini->ping();
            $this->line("  <fg=green>[OK]</> Conexão com a API do Gemini validada (SSL + autenticação). Resposta: \"{$reply}\"");

            return true;
        } catch (GeminiException $e) {
            $this->line("  <fg=red>[ERRO]</> Falha ao conectar com o Gemini: {$e->getMessage()}");

            return false;
        }
    }
}
