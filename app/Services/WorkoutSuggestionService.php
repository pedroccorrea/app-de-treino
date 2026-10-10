<?php

namespace App\Services;

use App\DataTransferObjects\OverloadSuggestion;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Support\Collection;

/**
 * Junta as duas fontes de sugestão de carga da sessão ativa:
 * - exercícios com histórico próprio → progressão normal da Phase 42
 *   (ProgressiveOverloadService, inalterada);
 * - exercícios NOVOS para o usuário → carga inicial por exercícios parecidos
 *   ou "Primeira vez" (StartingLoadService).
 *
 * "Novo" é decidido pelo histórico do EXERCÍCIO, não da ficha: um exercício
 * que o usuário já fez em outra ficha ou programa (inclusive sob grafia
 * equivalente) nunca é tratado como novo só porque o programa mudou.
 */
class WorkoutSuggestionService
{
    public function __construct(
        private readonly ProgressiveOverloadService $progressiveOverloadService,
        private readonly StartingLoadService $startingLoadService,
        private readonly RecentPerformanceService $recentPerformanceService,
    ) {}

    /**
     * @return Collection<int, OverloadSuggestion>
     */
    public function suggestionsFor(User $user, Workout $workout): Collection
    {
        $progression = $this->progressiveOverloadService->analyzeWorkout($user, $workout);

        $withHistory = $this->recentPerformanceService->exerciseIdsWithHistory($user, $workout->exercises->pluck('id')->all());
        $newExercises = $workout->exercises
            ->reject(fn (Exercise $exercise) => in_array($exercise->id, $withHistory, true))
            ->values();

        if ($newExercises->isEmpty()) {
            return $progression;
        }

        $newIds = $newExercises->pluck('id')->all();

        return $progression
            ->reject(fn (OverloadSuggestion $suggestion) => in_array($suggestion->exercise_id, $newIds, true))
            ->concat($this->startingLoadService->suggest($user, $newExercises))
            ->values();
    }
}
