<?php

namespace App\Services;

use App\Enums\HistoryRange;
use App\Models\Exercise;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Support\Collection;

class ExerciseHistoryService
{
    /**
     * Completed sessions where this exercise was logged, within the given
     * range, most recent first. Each session is eager-loaded with only the
     * set_logs belonging to this exercise, ordered by set_number.
     *
     * @return Collection<int, WorkoutSession>
     */
    public function getHistory(User $user, Exercise $exercise, HistoryRange $range): Collection
    {
        return WorkoutSession::query()
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $range->startDate())
            ->whereHas('setLogs', fn ($query) => $query->where('exercise_id', $exercise->id))
            ->with(['setLogs' => fn ($query) => $query
                ->where('exercise_id', $exercise->id)
                ->orderBy('set_number'),
            ])
            ->orderByDesc('completed_at')
            ->get();
    }

    /**
     * Total volume (carga × reps, somada entre todas as séries do exercício)
     * por sessão, em ordem cronológica — pronto para alimentar o gráfico de
     * progressão.
     *
     * @return array<int, array{session_id: int, date: string, volume: float}>
     */
    public function getVolumeSeries(User $user, Exercise $exercise, HistoryRange $range): array
    {
        return $this->getHistory($user, $exercise, $range)
            ->sortBy('completed_at')
            ->map(fn (WorkoutSession $session) => [
                'session_id' => $session->id,
                'date' => $session->completed_at->toDateString(),
                'volume' => (float) $session->setLogs->sum(
                    fn (SetLog $log) => (float) ($log->weight ?? 0) * $log->reps
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * A maior carga já registrada pelo usuário neste exercício (em qualquer
     * sessão concluída, independente do período) e a data em que ocorreu.
     *
     * @return array{weight: float, date: string}|null
     */
    public function getPersonalRecord(User $user, Exercise $exercise): ?array
    {
        $record = SetLog::query()
            ->where('exercise_id', $exercise->id)
            ->whereNotNull('weight')
            ->whereHas('workoutSession', fn ($query) => $query
                ->where('user_id', $user->id)
                ->whereNotNull('completed_at')
            )
            ->with('workoutSession')
            ->orderByDesc('weight')
            ->orderByDesc('created_at')
            ->first();

        if (! $record) {
            return null;
        }

        return [
            'weight' => (float) $record->weight,
            'date' => $record->workoutSession->completed_at->toDateString(),
        ];
    }
}
