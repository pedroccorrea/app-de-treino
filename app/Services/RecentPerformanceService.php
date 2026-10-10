<?php

namespace App\Services;

use App\DataTransferObjects\RecentPerformanceSummary;
use App\Enums\PerformanceTrend;
use App\Models\SetLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Resume em PHP o histórico recente de exercícios, sem IA e sem mandar o
 * histórico inteiro para lugar nenhum:
 * - a melhor série de cada uma das últimas sessões (poucas, limitado);
 * - a referência = o MELHOR desempenho entre essas sessões, para que um
 *   treino isolado mais fraco não puxe nenhuma sugestão para baixo;
 * - a tendência (subindo, estável ou caindo).
 *
 * Só entra o que já conta como histórico no app: sessões concluídas, séries
 * de trabalho (aquecimento fora) e com carga. O histórico é do exercício,
 * não da ficha: vale qualquer treino ou programa, inclusive sob uma grafia
 * equivalente do nome (ExerciseIdentityService).
 */
class RecentPerformanceService
{
    public const DEFAULT_MAX_SESSIONS = 3;

    /**
     * Variação relativa (no 1RM estimado) abaixo da qual a tendência é
     * considerada estável.
     */
    private const TREND_TOLERANCE = 0.025;

    public function __construct(private readonly ExerciseIdentityService $exerciseIdentityService) {}

    /**
     * @param  array<int, int>  $exerciseIds
     * @return Collection<int, RecentPerformanceSummary> indexada por exercise_id; exercícios sem histórico com carga ficam de fora
     */
    public function summarize(User $user, array $exerciseIds, int $maxSessions = self::DEFAULT_MAX_SESSIONS): Collection
    {
        $equivalents = $this->exerciseIdentityService->equivalentIds($user, $exerciseIds);

        return collect($equivalents)
            ->map(fn (array $ids, int $exerciseId) => $this->summarizeOne($user, $exerciseId, $ids, max(1, $maxSessions)))
            ->filter();
    }

    /**
     * Quais dos exercícios informados já têm histórico próprio (qualquer
     * série de trabalho numa sessão concluída, em qualquer ficha ou programa,
     * inclusive sob grafia equivalente). Exercício que não está aqui é
     * "novo" para o usuário.
     *
     * @param  array<int, int>  $exerciseIds
     * @return list<int>
     */
    public function exerciseIdsWithHistory(User $user, array $exerciseIds): array
    {
        $equivalents = $this->exerciseIdentityService->equivalentIds($user, $exerciseIds, exclusive: false);

        if ($equivalents === []) {
            return [];
        }

        $logged = SetLog::query()
            ->join('workout_sessions', 'workout_sessions.id', '=', 'set_logs.workout_session_id')
            ->where('workout_sessions.user_id', $user->id)
            ->whereNotNull('workout_sessions.completed_at')
            ->where('set_logs.is_warmup', false)
            ->whereIn('set_logs.exercise_id', collect($equivalents)->flatten()->unique()->values()->all())
            ->distinct()
            ->pluck('set_logs.exercise_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return collect($equivalents)
            ->filter(fn (array $ids) => array_intersect($ids, $logged) !== [])
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $ids  o exercício e suas grafias equivalentes
     */
    private function summarizeOne(User $user, int $exerciseId, array $ids, int $maxSessions): ?RecentPerformanceSummary
    {
        $recentSessions = $this->workingSets($user, $ids)
            ->groupBy('set_logs.workout_session_id')
            ->selectRaw('set_logs.workout_session_id, MAX(workout_sessions.completed_at) as completed_at')
            ->orderByDesc('completed_at')
            ->limit($maxSessions)
            ->get();

        if ($recentSessions->isEmpty()) {
            return null;
        }

        $setsBySession = $this->workingSets($user, $ids)
            ->whereIn('set_logs.workout_session_id', $recentSessions->pluck('workout_session_id')->all())
            ->get(['set_logs.workout_session_id', 'set_logs.weight', 'set_logs.reps'])
            ->groupBy('workout_session_id');

        $sessions = $recentSessions
            ->map(function ($row) use ($setsBySession) {
                $best = $setsBySession->get($row->workout_session_id, collect())
                    ->map(fn ($set) => ['weight' => (float) $set->weight, 'reps' => (int) $set->reps])
                    ->sort(fn (array $a, array $b) => $this->compareSets($b, $a))
                    ->first();

                return $best === null ? null : [
                    ...$best,
                    'date' => substr((string) $row->completed_at, 0, 10),
                ];
            })
            ->filter()
            ->values();

        if ($sessions->isEmpty()) {
            return null;
        }

        return new RecentPerformanceSummary(
            exercise_id: $exerciseId,
            sessions: $sessions->all(),
            best: $sessions->sort(fn (array $a, array $b) => $this->compareSets($b, $a))->first(),
            trend: $this->trend($sessions),
        );
    }

    /**
     * Compara a mais recente com a mais antiga das sessões resumidas, pelo
     * 1RM estimado da melhor série de cada uma.
     *
     * @param  Collection<int, array{weight: float, reps: int, date: string}>  $sessions  mais recente primeiro
     */
    private function trend(Collection $sessions): PerformanceTrend
    {
        if ($sessions->count() < 2) {
            return PerformanceTrend::Stable;
        }

        $newest = $this->estimatedOneRepMax($sessions->first());
        $oldest = $this->estimatedOneRepMax($sessions->last());

        if ($oldest <= 0) {
            return PerformanceTrend::Stable;
        }

        $change = ($newest - $oldest) / $oldest;

        return match (true) {
            $change > self::TREND_TOLERANCE => PerformanceTrend::Rising,
            $change < -self::TREND_TOLERANCE => PerformanceTrend::Falling,
            default => PerformanceTrend::Stable,
        };
    }

    /**
     * "Melhor série" = maior 1RM estimado (Epley), desempate pela carga
     * maior. Assim 40 kg × 10 vale mais que 42,5 kg × 3.
     *
     * @param  array{weight: float, reps: int}  $a
     * @param  array{weight: float, reps: int}  $b
     */
    private function compareSets(array $a, array $b): int
    {
        return [$this->estimatedOneRepMax($a), $a['weight']] <=> [$this->estimatedOneRepMax($b), $b['weight']];
    }

    /**
     * @param  array{weight: float, reps: int}  $set
     */
    private function estimatedOneRepMax(array $set): float
    {
        return $set['weight'] * (1 + $set['reps'] / 30);
    }

    /**
     * Séries de trabalho com carga, em sessões concluídas do usuário.
     *
     * @param  list<int>  $ids
     * @return Builder<SetLog>
     */
    private function workingSets(User $user, array $ids): Builder
    {
        return SetLog::query()
            ->join('workout_sessions', 'workout_sessions.id', '=', 'set_logs.workout_session_id')
            ->where('workout_sessions.user_id', $user->id)
            ->whereNotNull('workout_sessions.completed_at')
            ->where('set_logs.is_warmup', false)
            ->whereNotNull('set_logs.weight')
            ->where('set_logs.weight', '>', 0)
            ->whereIn('set_logs.exercise_id', $ids);
    }
}
