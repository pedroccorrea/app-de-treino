<?php

namespace App\Services;

use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Histórico de treinos FINALIZADOS: leitura (Phase 44) e correções — editar
 * série, excluir exercício de um treino, excluir treino (Phase 45).
 *
 * Nada aqui é salvo no banco: volume, duração e o agrupamento por exercício
 * são calculados na hora a partir de `set_logs`. O mesmo vale para "Última",
 * recordes, sugestões e estatísticas nos outros services — por isso editar
 * ou excluir uma série já se reflete em todo o app sem recalcular nada.
 *
 * Exclusões são recuperáveis (SoftDeletes): o Eloquent esconde as linhas
 * excluídas de toda consulta, e a linha que fica no banco é o que impede a
 * fila offline de recriá-las (ver `SyncService::logSet`).
 */
class WorkoutHistoryService
{
    /**
     * Acima disso a duração deixa de ser confiável: sessões esquecidas
     * abertas são encerradas depois pela limpeza de sessões fantasmas
     * (`WorkoutSessionService::closeOpenSessionsForUser`), e o
     * `completed_at` delas é a hora da limpeza, não a do fim do treino.
     */
    private const MAX_PLAUSIBLE_DURATION_MINUTES = 300;

    /**
     * Sessões finalizadas do usuário, mais recentes primeiro. Sessões sem
     * nenhuma série registrada ficam de fora: são treinos abertos e
     * abandonados (encerrados pela limpeza de fantasmas), não treinos
     * executados. A sessão em andamento (`completed_at` nulo) nunca entra.
     */
    public function paginateCompletedSessions(User $user, int $perPage = 15): Paginator
    {
        return WorkoutSession::query()
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->whereHas('setLogs')
            ->with(['workout:id,name', 'setLogs.exercise:id,name'])
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->simplePaginate($perPage);
    }

    /**
     * Carrega uma sessão finalizada com tudo o que a tela de detalhe precisa.
     */
    public function loadSessionDetail(WorkoutSession $session): WorkoutSession
    {
        return $session->load(['workout:id,name', 'setLogs.exercise:id,name,equipment']);
    }

    /**
     * Agrupa as séries por exercício, na ordem em que o usuário executou
     * cada um (a primeira série registrada de cada exercício) — que é a
     * ordem real do treino, mesmo que ele tenha pulado ou trocado a ordem
     * da ficha. Dentro de cada exercício, as séries seguem `set_number`.
     *
     * @return Collection<int, array{exercise_id: int, exercise_name: string, equipment: string|null, sets: Collection<int, SetLog>, volume: float}>
     */
    public function exerciseBreakdown(WorkoutSession $session): Collection
    {
        return $session->setLogs
            ->sortBy([['created_at', 'asc'], ['id', 'asc']])
            ->groupBy('exercise_id')
            ->map(fn (Collection $logs) => [
                'exercise_id' => $logs->first()->exercise_id,
                'exercise_name' => $logs->first()->exercise?->name ?? 'Exercício',
                'equipment' => $logs->first()->exercise?->equipment?->value,
                'sets' => $logs->sortBy('set_number')->values(),
                'volume' => $this->volume($logs),
            ])
            ->values();
    }

    /**
     * Corrige carga e repetições de uma série já registrada. `edited_at`
     * marca a correção: um `log_set` antigo da fila offline, ocorrido antes
     * dela, não pode desfazê-la (ver `SyncService::logSet`).
     *
     * @param  array{weight?: float|int|string|null, reps: int|string}  $data
     */
    public function updateSet(SetLog $log, array $data): SetLog
    {
        $log->forceFill([
            'weight' => $data['weight'] ?? null,
            'reps' => (int) $data['reps'],
            'edited_at' => now(),
        ])->save();

        return $log;
    }

    /**
     * Exclui do treino todas as séries de um exercício. A ficha e o
     * exercício do catálogo não são tocados. Se era o último exercício do
     * treino, o treino inteiro sai junto — um treino sem nenhuma série não é
     * um treino executado (a lista já o esconderia), mas ainda contaria na
     * sequência de dias do Dashboard.
     *
     * @return bool true quando o treino inteiro também foi excluído
     */
    public function deleteExercise(WorkoutSession $session, int $exerciseId): bool
    {
        return DB::transaction(function () use ($session, $exerciseId) {
            $session->setLogs()->where('exercise_id', $exerciseId)->delete();

            if ($session->setLogs()->doesntExist()) {
                $session->delete();

                return true;
            }

            return false;
        });
    }

    /**
     * Exclui um treino do histórico com todas as suas séries. A ficha
     * (`Workout`) continua existindo.
     */
    public function deleteSession(WorkoutSession $session): void
    {
        DB::transaction(function () use ($session) {
            $session->setLogs()->delete();
            $session->delete();
        });
    }

    /**
     * Volume total (carga × reps somado). Séries sem carga contam como zero,
     * mesma regra de `ExerciseHistoryService::getVolumeSeries`.
     *
     * @param  Collection<int, SetLog>  $logs
     */
    public function volume(Collection $logs): float
    {
        return (float) $logs->sum(fn (SetLog $log) => (float) ($log->weight ?? 0) * $log->reps);
    }

    /**
     * Duração em minutos, ou null quando não é confiável (ver
     * MAX_PLAUSIBLE_DURATION_MINUTES).
     */
    public function durationMinutes(WorkoutSession $session): ?int
    {
        if (! $session->started_at || ! $session->completed_at) {
            return null;
        }

        $minutes = (int) round($session->started_at->diffInMinutes($session->completed_at));

        return $minutes > 0 && $minutes <= self::MAX_PLAUSIBLE_DURATION_MINUTES ? $minutes : null;
    }
}
