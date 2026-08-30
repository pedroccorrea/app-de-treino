<?php

namespace App\Services;

use App\DataTransferObjects\OverloadSuggestion;
use App\Enums\OverloadConfidence;
use App\Exceptions\GeminiException;
use App\Models\SetLog;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSession;
use App\Services\AI\GeminiClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProgressiveOverloadService
{
    public function __construct(private readonly GeminiClient $gemini) {}

    /**
     * Analyzes the user's 3 most recent completed sessions of this workout and
     * asks Gemini for progressive-overload advice. Read-only: nothing is
     * written to the database during the analysis.
     *
     * The AI response is only ever consumed as structured JSON matched by
     * exercise_id (never by exercise name). Any suggestion the AI invents for
     * an exercise_id outside this workout is dropped and logged; any response
     * that isn't valid/complete JSON degrades to an empty collection instead
     * of throwing, so the screen simply shows no suggestions.
     *
     * @return Collection<int, OverloadSuggestion>
     */
    public function analyzeWorkout(User $user, Workout $workout): Collection
    {
        $sessions = $this->recentCompletedSessions($user, $workout);

        if ($sessions->isEmpty()) {
            return collect();
        }

        $workoutExerciseIds = $workout->exercises->pluck('id')->all();
        $previousPerformance = $this->previousPerformanceByExercise($sessions);

        try {
            $response = $this->gemini->generate($this->buildPrompt($sessions, $workout));
        } catch (GeminiException) {
            return collect();
        }

        return $this->mapRecommendations($response, $workoutExerciseIds, $previousPerformance);
    }

    /**
     * @return Collection<int, WorkoutSession>
     */
    private function recentCompletedSessions(User $user, Workout $workout): Collection
    {
        return $workout->workoutSessions()
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->with(['setLogs.exercise'])
            ->orderByDesc('completed_at')
            ->limit(3)
            ->get();
    }

    /**
     * The "previous performance" shown for each exercise is its first logged
     * set (lowest set_number) in the most recent completed session where it
     * appears — $sessions is already ordered most-recent-first, so the first
     * match found per exercise_id wins.
     *
     * @param  Collection<int, WorkoutSession>  $sessions
     * @return array<int, array{weight: ?float, reps: int}>
     */
    private function previousPerformanceByExercise(Collection $sessions): array
    {
        $result = [];

        foreach ($sessions as $session) {
            foreach ($session->setLogs->sortBy('set_number') as $log) {
                if (array_key_exists($log->exercise_id, $result)) {
                    continue;
                }

                $result[$log->exercise_id] = [
                    'weight' => $log->weight !== null ? (float) $log->weight : null,
                    'reps' => $log->reps,
                ];
            }
        }

        return $result;
    }

    /**
     * @param  Collection<int, WorkoutSession>  $sessions
     */
    private function buildPrompt(Collection $sessions, Workout $workout): string
    {
        $exercises = $workout->exercises->map(fn ($exercise) => [
            'exercise_id' => $exercise->id,
            'name' => $exercise->name,
        ])->values()->all();

        $history = $sessions->map(fn (WorkoutSession $session) => [
            'date' => $session->completed_at?->toDateString(),
            'sets' => $session->setLogs
                ->sortBy([['exercise_id', 'asc'], ['set_number', 'asc']])
                ->map(fn (SetLog $set) => [
                    'exercise_id' => $set->exercise_id,
                    'exercise' => $set->exercise->name,
                    'set_number' => $set->set_number,
                    'weight' => $set->weight !== null ? (float) $set->weight : null,
                    'reps' => $set->reps,
                    'rpe' => $set->rpe !== null ? (float) $set->rpe : null,
                ])->values()->all(),
        ])->all();

        $exercisesJson = json_encode($exercises, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $historyJson = json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
        Você é um personal trainer especialista em treinamento de força e sobrecarga progressiva.

        Estes são os exercícios desta ficha de treino, cada um identificado por um exercise_id numérico:
        {$exercisesJson}

        Abaixo está o histórico estruturado das últimas sessões de treino concluídas pelo usuário nesta ficha,
        com as séries, repetições, cargas (kg) e percepção de esforço (RPE) registradas em cada exercício
        (cada série já vem identificada pelo exercise_id do exercício correspondente).

        Histórico (mais recente primeiro):
        {$historyJson}

        Para cada exercise_id presente no histórico, sugira a próxima carga e repetições, com uma justificativa
        (rationale) curta baseada no desempenho recente. Se o usuário completou as séries com folga, sugira aumento
        de carga. Se teve dificuldade ou RPE alto, mantenha a carga e foque em técnica. Informe também seu grau de
        confiança nessa sugestão.

        Use exclusivamente os exercise_id listados acima, nunca invente um exercise_id novo.

        Responda SOMENTE com um JSON no seguinte formato, sem markdown, sem comentários e sem texto adicional:
        {
          "recommendations": [
            {
              "exercise_id": 1,
              "suggested_load": 42.5,
              "suggested_reps": 8,
              "rationale": "string",
              "confidence": "high"
            }
          ]
        }
        PROMPT;
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array<int, int>  $workoutExerciseIds
     * @param  array<int, array{weight: ?float, reps: int}>  $previousPerformance
     * @return Collection<int, OverloadSuggestion>
     */
    private function mapRecommendations(array $response, array $workoutExerciseIds, array $previousPerformance): Collection
    {
        $recommendations = $response['recommendations'] ?? null;

        if (! is_array($recommendations)) {
            return collect();
        }

        return collect($recommendations)
            ->map(fn (mixed $recommendation) => $this->toSuggestion($recommendation, $workoutExerciseIds, $previousPerformance))
            ->filter()
            ->values();
    }

    /**
     * Builds a single OverloadSuggestion from one AI recommendation entry, or
     * returns null when it should be dropped: missing/invalid required
     * fields are dropped silently, while an exercise_id that doesn't belong
     * to this workout is dropped and logged (the AI must never be trusted to
     * only suggest exercises that were actually sent to it).
     *
     * @param  array<int, int>  $workoutExerciseIds
     * @param  array<int, array{weight: ?float, reps: int}>  $previousPerformance
     */
    private function toSuggestion(mixed $recommendation, array $workoutExerciseIds, array $previousPerformance): ?OverloadSuggestion
    {
        if (! is_array($recommendation)) {
            return null;
        }

        $exerciseId = $recommendation['exercise_id'] ?? null;
        $suggestedLoad = $recommendation['suggested_load'] ?? null;
        $suggestedReps = $recommendation['suggested_reps'] ?? null;
        $rationale = $recommendation['rationale'] ?? null;

        if (! is_numeric($exerciseId) || ! is_numeric($suggestedLoad) || ! is_numeric($suggestedReps)) {
            return null;
        }

        if (! is_string($rationale) || trim($rationale) === '') {
            return null;
        }

        $exerciseId = (int) $exerciseId;

        if (! in_array($exerciseId, $workoutExerciseIds, true)) {
            Log::warning('Sugestão de sobrecarga progressiva descartada: exercise_id não pertence ao treino.', [
                'exercise_id' => $exerciseId,
                'workout_exercise_ids' => $workoutExerciseIds,
            ]);

            return null;
        }

        $previous = $previousPerformance[$exerciseId] ?? null;

        return new OverloadSuggestion(
            exercise_id: $exerciseId,
            suggested_load: (float) $suggestedLoad,
            suggested_reps: (int) $suggestedReps,
            previous_load: $previous['weight'] ?? null,
            previous_reps: $previous['reps'] ?? null,
            rationale: $rationale,
            confidence: OverloadConfidence::fromAi($recommendation['confidence'] ?? null),
        );
    }
}
