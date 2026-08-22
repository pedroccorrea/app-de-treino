<?php

namespace App\Services;

use App\Enums\MuscleGroup;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Services\AI\GeminiClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardAnalyticsService
{
    public function __construct(private readonly GeminiClient $gemini) {}

    /**
     * Counts the number of consecutive days (ending today or yesterday) in
     * which the user completed at least one workout session.
     */
    public function calculateStreak(User $user): int
    {
        $completedDates = $user->workoutSessions()
            ->whereNotNull('completed_at')
            ->get()
            ->map(fn (WorkoutSession $session) => $session->completed_at->toDateString())
            ->unique()
            ->flip();

        if ($completedDates->isEmpty()) {
            return 0;
        }

        $cursor = Carbon::today();

        // Allow the streak to still count if the user simply hasn't trained
        // yet today, as long as yesterday is covered.
        if (! $completedDates->has($cursor->toDateString())) {
            $cursor = $cursor->copy()->subDay();
        }

        $streak = 0;

        while ($completedDates->has($cursor->toDateString())) {
            $streak++;
            $cursor = $cursor->copy()->subDay();
        }

        return $streak;
    }

    /**
     * The heaviest non-warmup set ever logged for each exercise, most
     * recently achieved first.
     *
     * @return array<int, array{exercise_id: int, exercise_name: string, weight: float, reps: int, achieved_at: ?string}>
     */
    public function calculatePersonalRecords(User $user, int $limit = 5): array
    {
        $setLogs = SetLog::query()
            ->whereHas('workoutSession', fn ($query) => $query->where('user_id', $user->id))
            ->where('is_warmup', false)
            ->whereNotNull('weight')
            ->with(['exercise', 'workoutSession'])
            ->get();

        return $setLogs
            ->groupBy('exercise_id')
            ->map(fn (Collection $logs) => $logs->sortByDesc(fn (SetLog $log) => (float) $log->weight)->first())
            ->sortByDesc(fn (SetLog $log) => $log->workoutSession->completed_at ?? $log->created_at)
            ->take($limit)
            ->map(fn (SetLog $log) => [
                'exercise_id' => $log->exercise_id,
                'exercise_name' => $log->exercise->name,
                'weight' => (float) $log->weight,
                'reps' => $log->reps,
                'achieved_at' => ($log->workoutSession->completed_at ?? $log->created_at)?->toDateString(),
            ])
            ->values()
            ->all();
    }

    /**
     * Total non-warmup sets performed this week, grouped by primary muscle
     * group. Every muscle group is present, even with zero sets, so the
     * frontend can render a complete bar chart.
     *
     * @return array<string, int>
     */
    public function calculateWeeklyVolumeByMuscleGroup(User $user, ?Carbon $referenceDate = null): array
    {
        $referenceDate ??= Carbon::now();
        $startOfWeek = $referenceDate->copy()->startOfWeek();
        $endOfWeek = $referenceDate->copy()->endOfWeek();

        $setLogs = SetLog::query()
            ->whereHas('workoutSession', function ($query) use ($user, $startOfWeek, $endOfWeek) {
                $query->where('user_id', $user->id)
                    ->whereBetween('completed_at', [$startOfWeek, $endOfWeek]);
            })
            ->where('is_warmup', false)
            ->with('exercise')
            ->get();

        $volume = [];

        foreach (MuscleGroup::cases() as $muscleGroup) {
            $volume[$muscleGroup->value] = 0;
        }

        foreach ($setLogs as $setLog) {
            $muscleGroup = $setLog->exercise->primary_muscle_group;
            $volume[$muscleGroup->value]++;
        }

        return $volume;
    }

    /**
     * Number of completed sessions per day over the last `$weeks` weeks, used
     * to render a GitHub-style contribution heatmap.
     *
     * @return array<int, array{date: string, count: int}>
     */
    public function calculateActivityHeatmap(User $user, int $weeks = 12): array
    {
        $startDate = Carbon::today()->subWeeks($weeks - 1)->startOfWeek();

        $countsByDate = $user->workoutSessions()
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $startDate)
            ->get()
            ->map(fn (WorkoutSession $session) => $session->completed_at->toDateString())
            ->countBy();

        $heatmap = [];
        $cursor = $startDate->copy();
        $today = Carbon::today();

        while ($cursor->lte($today)) {
            $date = $cursor->toDateString();
            $heatmap[] = [
                'date' => $date,
                'count' => $countsByDate->get($date, 0),
            ];
            $cursor->addDay();
        }

        return $heatmap;
    }

    /**
     * Asks Gemini for a muscle-balance assessment based on this week's
     * training volume, flagging neglected muscle groups relative to
     * dominant ones. Read-only: nothing is written to the database.
     *
     * @return array{status: string, neglected_muscles: array<int, string>, dominant_muscles: array<int, string>, summary: string}
     */
    public function analyzeMuscleBalance(User $user): array
    {
        $weeklyVolume = $this->calculateWeeklyVolumeByMuscleGroup($user);

        return $this->generateMuscleBalanceAlertWithGemini($weeklyVolume);
    }

    /**
     * @param  array<string, int>  $weeklyVolume
     * @return array<string, mixed>
     */
    private function generateMuscleBalanceAlertWithGemini(array $weeklyVolume): array
    {
        return $this->gemini->generate($this->buildMuscleBalancePrompt($weeklyVolume));
    }

    /**
     * @param  array<string, int>  $weeklyVolume
     */
    private function buildMuscleBalancePrompt(array $weeklyVolume): string
    {
        $muscleGroups = implode(', ', array_map(fn (MuscleGroup $case) => $case->value, MuscleGroup::cases()));
        $volumeJson = json_encode($weeklyVolume, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
        Você é um personal trainer especialista em prevenção de desequilíbrios musculares e assimetrias posturais.

        Abaixo está o volume de séries (não incluindo aquecimento) realizadas pelo usuário na semana atual,
        agrupado por grupo muscular principal (valores possíveis: {$muscleGroups}):
        {$volumeJson}

        Analise se há grupos musculares negligenciados (poucas ou nenhuma série) em relação a grupos dominantes
        (com volume muito maior), o que pode indicar risco de desequilíbrio postural ou assimetria muscular.

        Responda SOMENTE com um JSON no seguinte formato, sem markdown, sem comentários e sem texto adicional:
        {
          "status": "ok ou warning",
          "neglected_muscles": ["string"],
          "dominant_muscles": ["string"],
          "summary": "string"
        }
        PROMPT;
    }
}
