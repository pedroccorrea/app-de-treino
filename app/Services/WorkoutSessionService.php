<?php

namespace App\Services;

use App\Models\SetLog;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSession;
use Illuminate\Support\Collection;

class WorkoutSessionService
{
    public function startSession(User $user, Workout $workout): WorkoutSession
    {
        return $user->workoutSessions()->create([
            'workout_id' => $workout->id,
            'started_at' => now(),
            'completed_at' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function logSet(WorkoutSession $session, array $data): SetLog
    {
        return $session->setLogs()->updateOrCreate(
            [
                'exercise_id' => $data['exercise_id'],
                'set_number' => $data['set_number'],
            ],
            [
                'weight' => $data['weight'] ?? null,
                'reps' => $data['reps'],
                'rpe' => $data['rpe'] ?? null,
                'is_warmup' => (bool) ($data['is_warmup'] ?? false),
                'notes' => $data['notes'] ?? null,
            ]
        );
    }

    public function finishSession(WorkoutSession $session): WorkoutSession
    {
        $session->update([
            'completed_at' => now(),
        ]);

        return $session;
    }

    /**
     * Busca o histórico recente (último treino) para cada exercício informado.
     *
     * @param array<int> $exerciseIds
     * @return array<int, array{summary: string, weight: float|null, reps: int|null, sets: array<int, array{set_number: int, weight: float|null, reps: int, rpe: float|null}>}>
     */
    public function getLastLogsForExercises(User $user, array $exerciseIds, ?int $excludeSessionId = null): array
    {
        if (empty($exerciseIds)) {
            return [];
        }

        $query = SetLog::query()
            ->with('workoutSession')
            ->whereHas('workoutSession', function ($q) use ($user, $excludeSessionId) {
                $q->where('user_id', $user->id);
                if ($excludeSessionId) {
                    $q->where('id', '!=', $excludeSessionId);
                }
            })
            ->whereIn('exercise_id', $exerciseIds)
            ->orderByDesc('created_at');

        /** @var Collection<int, SetLog> $logs */
        $logs = $query->get();

        $result = [];

        foreach ($exerciseIds as $exerciseId) {
            $exerciseLogs = $logs->where('exercise_id', $exerciseId);

            if ($exerciseLogs->isEmpty()) {
                continue;
            }

            // Pega a sessão mais recente onde esse exercício foi realizado
            $latestSessionId = $exerciseLogs->first()->workout_session_id;
            $sessionSets = $exerciseLogs
                ->where('workout_session_id', $latestSessionId)
                ->sortBy('set_number')
                ->values();

            $mainSet = $sessionSets->first();
            $weightText = $mainSet && $mainSet->weight !== null ? ((float) $mainSet->weight) . 'kg' : 'Peso corporal';
            $repsText = $mainSet ? "{$mainSet->reps} reps" : '';
            $summary = trim("{$weightText} × {$repsText}", ' ×');

            $result[$exerciseId] = [
                'summary' => $summary,
                'weight' => $mainSet?->weight ? (float) $mainSet->weight : null,
                'reps' => $mainSet?->reps,
                'sets' => $sessionSets->map(fn(SetLog $set) => [
                    'set_number' => $set->set_number,
                    'weight' => $set->weight !== null ? (float) $set->weight : null,
                    'reps' => $set->reps,
                    'rpe' => $set->rpe !== null ? (float) $set->rpe : null,
                ])->all(),
            ];
        }

        return $result;
    }
}
