<?php

namespace App\Console\Commands;

use App\Services\WorkoutSessionService;
use Illuminate\Console\Command;

/**
 * Limpeza pontual para bases já existentes: fecha toda sessão de treino que
 * ficou aberta indevidamente (completed_at nulo) por conta de bugs ou testes
 * antigos, evitando que o banner global fique "preso" numa sessão fantasma.
 */
class CloseGhostWorkoutSessionsCommand extends Command
{
    protected $signature = 'sessions:close-ghosts';

    protected $description = 'Encerra sessões de treino fantasmas que ficaram abertas (completed_at nulo) no banco';

    public function handle(WorkoutSessionService $service): int
    {
        $closed = $service->closeAllGhostSessions();

        $this->info("✅ {$closed} sessão(ões) fantasma(s) encerrada(s).");

        return self::SUCCESS;
    }
}
