<?php

namespace App\Http\Controllers;

use App\Models\SetLog;
use App\Models\WorkoutSession;
use App\Services\WorkoutHistoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkoutHistoryController extends Controller
{
    public function index(Request $request, WorkoutHistoryService $service): Response
    {
        $paginator = $service->paginateCompletedSessions($request->user());

        return Inertia::render('WorkoutHistory/Index', [
            // `merge`: o botão "Carregar mais" faz um partial reload da
            // próxima página e o Inertia ANEXA os itens aos já exibidos, em
            // vez de substituí-los.
            'sessions' => Inertia::merge(
                collect($paginator->items())
                    ->map(fn (WorkoutSession $session) => $this->formatSummary($session, $service))
                    ->values()
                    ->all()
            ),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }

    public function show(Request $request, WorkoutSession $session, WorkoutHistoryService $service): Response
    {
        abort_if($session->user_id !== $request->user()->id, 403);
        // O treino em andamento só existe na tela de treino, nunca no histórico.
        abort_if($session->completed_at === null, 404);

        $session = $service->loadSessionDetail($session);

        return Inertia::render('WorkoutHistory/Show', [
            'session' => [
                ...$this->formatSummary($session, $service),
                'exercises' => $this->formatExercises($session, $service),
            ],
        ]);
    }

    /**
     * @return array{id: int, workout_id: int|null, workout_name: string|null, completed_at: string, started_at: string|null, duration_minutes: int|null, exercise_names: array<int, string>, exercise_count: int, set_count: int, volume: float}
     */
    private function formatSummary(WorkoutSession $session, WorkoutHistoryService $service): array
    {
        $exerciseNames = $service->exerciseBreakdown($session)->pluck('exercise_name')->all();

        return [
            'id' => $session->id,
            'workout_id' => $session->workout_id,
            // null quando a ficha foi excluída (`nullOnDelete`); a tela
            // mostra um texto neutro no lugar.
            'workout_name' => $session->workout?->name,
            'completed_at' => $session->completed_at->toISOString(),
            'started_at' => $session->started_at?->toISOString(),
            'duration_minutes' => $service->durationMinutes($session),
            'exercise_names' => $exerciseNames,
            'exercise_count' => count($exerciseNames),
            'set_count' => $session->setLogs->count(),
            'volume' => $service->volume($session->setLogs),
        ];
    }

    /**
     * @return array<int, array{exercise_id: int, name: string, volume: float, sets: array<int, array{id: int, set_number: int, weight: float|null, reps: int, is_warmup: bool}>}>
     */
    private function formatExercises(WorkoutSession $session, WorkoutHistoryService $service): array
    {
        return $service->exerciseBreakdown($session)
            ->map(fn (array $group) => [
                'exercise_id' => $group['exercise_id'],
                'name' => $group['exercise_name'],
                'volume' => $group['volume'],
                'sets' => $group['sets']->map(fn (SetLog $log) => [
                    'id' => $log->id,
                    'set_number' => $log->set_number,
                    'weight' => $log->weight !== null ? (float) $log->weight : null,
                    'reps' => $log->reps,
                    'is_warmup' => (bool) $log->is_warmup,
                ])->all(),
            ])
            ->all();
    }
}
