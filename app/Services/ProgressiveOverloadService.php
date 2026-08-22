<?php

namespace App\Services;

use App\Models\SetLog;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSession;
use App\Services\AI\GeminiClient;

class ProgressiveOverloadService
{
    public function __construct(private readonly GeminiClient $gemini) {}

    /**
     * Analyzes the user's 3 most recent completed sessions of this workout and
     * asks Gemini for progressive-overload advice. Read-only: nothing is
     * written to the database during the analysis.
     *
     * @return array{recommendations: array<int, array{exercise_name: string, current_load: string, suggested_load: string, suggested_reps: string, rationale: string}>}
     */
    public function analyzeWorkout(User $user, Workout $workout): array
    {
        $history = $this->buildHistory($user, $workout);

        return $this->generateAdviceWithGemini($history);
    }

    /**
     * @return array<int, array{date: ?string, sets: array<int, array{exercise: string, set_number: int, weight: float|null, reps: int, rpe: float|null}>}>
     */
    private function buildHistory(User $user, Workout $workout): array
    {
        $sessions = $workout->workoutSessions()
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->with(['setLogs.exercise'])
            ->orderByDesc('completed_at')
            ->limit(3)
            ->get();

        return $sessions->map(fn (WorkoutSession $session) => [
            'date' => $session->completed_at?->toDateString(),
            'sets' => $session->setLogs
                ->sortBy([['exercise_id', 'asc'], ['set_number', 'asc']])
                ->map(fn (SetLog $set) => [
                    'exercise' => $set->exercise->name,
                    'set_number' => $set->set_number,
                    'weight' => $set->weight !== null ? (float) $set->weight : null,
                    'reps' => $set->reps,
                    'rpe' => $set->rpe !== null ? (float) $set->rpe : null,
                ])->values()->all(),
        ])->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $history
     * @return array{recommendations: array<int, array<string, string>>}
     */
    private function generateAdviceWithGemini(array $history): array
    {
        return $this->gemini->generate($this->buildPrompt($history));
    }

    /**
     * @param  array<int, array<string, mixed>>  $history
     */
    private function buildPrompt(array $history): string
    {
        $historyJson = json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
        Você é um personal trainer especialista em treinamento de força e sobrecarga progressiva.

        Abaixo está o histórico estruturado das últimas sessões de treino concluídas pelo usuário nesta ficha,
        com as séries, repetições, cargas (kg) e percepção de esforço (RPE) registradas em cada exercício.

        Histórico (mais recente primeiro):
        {$historyJson}

        Para cada exercício presente no histórico, sugira a próxima carga e faixa de repetições, com uma justificativa
        (rationale) curta baseada no desempenho recente. Se o usuário completou as séries com folga, sugira aumento de
        carga. Se teve dificuldade ou RPE alto, mantenha a carga e foque em técnica.

        Responda SOMENTE com um JSON no seguinte formato, sem markdown, sem comentários e sem texto adicional:
        {
          "recommendations": [
            {
              "exercise_name": "string",
              "current_load": "string",
              "suggested_load": "string",
              "suggested_reps": "string",
              "rationale": "string"
            }
          ]
        }
        PROMPT;
    }
}
