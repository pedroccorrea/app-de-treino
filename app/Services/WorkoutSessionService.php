<?php

namespace App\Services;

use App\Models\SetLog;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WorkoutSessionService
{
    /**
     * Inicia uma nova sessão de treino para o usuário.
     *
     * Antes de abrir a nova sessão, qualquer sessão anterior que tenha
     * ficado aberta (fantasma) é encerrada automaticamente, garantindo que
     * o usuário nunca tenha duas sessões ativas ao mesmo tempo e que o
     * banner global sempre reflita a sessão correta.
     */
    public function startSession(User $user, Workout $workout): WorkoutSession
    {
        return DB::transaction(function () use ($user, $workout) {
            $this->closeOpenSessionsForUser($user);

            return $user->workoutSessions()->create([
                'workout_id' => $workout->id,
                'started_at' => now(),
                'completed_at' => null,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
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

    /**
     * Finaliza a sessão informada e, de quebra, encerra qualquer outra
     * sessão órfã do mesmo usuário que tenha ficado com `completed_at`
     * nulo (resíduo de execuções anteriores), para que o banner global
     * nunca aponte para mais de uma sessão "ativa" ao mesmo tempo.
     */
    public function finishSession(WorkoutSession $session): WorkoutSession
    {
        return DB::transaction(function () use ($session) {
            $session->update([
                'completed_at' => now(),
            ]);

            $this->closeOpenSessionsForUser($session->user, $session->id);

            return $session;
        });
    }

    /**
     * Encerra todas as sessões abertas (`completed_at is null`) do usuário,
     * opcionalmente ignorando uma sessão específica. Usado tanto ao iniciar
     * quanto ao finalizar uma sessão, para eliminar sessões fantasmas.
     */
    private function closeOpenSessionsForUser(User $user, ?int $exceptSessionId = null): void
    {
        $user->workoutSessions()
            ->whereNull('completed_at')
            ->when($exceptSessionId, fn ($query) => $query->where('id', '!=', $exceptSessionId))
            ->update(['completed_at' => now()]);
    }

    /**
     * Fecha, no banco inteiro, todas as sessões que ficaram abertas
     * indevidamente (fantasmas de execuções/testes anteriores). Usado pelo
     * comando `sessions:close-ghosts` para higienizar dados já existentes.
     */
    public function closeAllGhostSessions(): int
    {
        return WorkoutSession::query()
            ->whereNull('completed_at')
            ->update(['completed_at' => now()]);
    }

    /**
     * Busca o histórico recente (último treino) para cada exercício informado.
     *
     * @param  array<int>  $exerciseIds
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
            $weightText = $mainSet && $mainSet->weight !== null ? ((float) $mainSet->weight).'kg' : 'Peso corporal';
            $repsText = $mainSet ? "{$mainSet->reps} reps" : '';
            $summary = trim("{$weightText} × {$repsText}", ' ×');

            $result[$exerciseId] = [
                'summary' => $summary,
                'weight' => $mainSet?->weight ? (float) $mainSet->weight : null,
                'reps' => $mainSet?->reps,
                'sets' => $sessionSets->map(fn (SetLog $set) => [
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
