<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSetLogRequest;
use App\Models\Exercise;
use App\Models\SetLog;
use App\Models\WorkoutSession;
use App\Services\WorkoutHistoryService;
use Illuminate\Http\RedirectResponse;
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
        $this->ensureOwnedCompletedSession($request, $session);

        $session = $service->loadSessionDetail($session);

        return Inertia::render('WorkoutHistory/Show', [
            'session' => [
                ...$this->formatSummary($session, $service),
                'exercises' => $this->formatExercises($session, $service),
            ],
        ]);
    }

    public function updateSet(UpdateSetLogRequest $request, WorkoutSession $session, SetLog $setLog, WorkoutHistoryService $service): RedirectResponse
    {
        $this->ensureOwnedCompletedSession($request, $session);
        // A série precisa pertencer a ESTE treino (não basta ser do usuário).
        abort_if($setLog->workout_session_id !== $session->id, 404);

        $service->updateSet($setLog, $request->validated());

        return redirect()->back()->with('success', 'Série atualizada.');
    }

    public function destroyExercise(Request $request, WorkoutSession $session, Exercise $exercise, WorkoutHistoryService $service): RedirectResponse
    {
        $this->ensureOwnedCompletedSession($request, $session);
        abort_if($session->setLogs()->where('exercise_id', $exercise->id)->doesntExist(), 404);

        $sessionRemoved = $service->deleteExercise($session, $exercise->id);

        // Era o último exercício: o treino saiu junto e o detalhe dele não
        // existe mais — volta para a origem, como na exclusão do treino.
        if ($sessionRemoved) {
            return redirect()
                ->to($this->safeRedirectTarget($request->query('return_to'), route('history.index')))
                ->with('success', 'Exercício excluído. O treino ficou sem séries e saiu do histórico.');
        }

        return redirect()->back()->with('success', 'Exercício excluído deste treino.');
    }

    public function destroy(Request $request, WorkoutSession $session, WorkoutHistoryService $service): RedirectResponse
    {
        $this->ensureOwnedCompletedSession($request, $session);

        $service->deleteSession($session);

        return redirect()
            ->to($this->safeRedirectTarget($request->query('return_to'), route('history.index')))
            ->with('success', 'Treino excluído do histórico.');
    }

    /**
     * Só o dono vê ou corrige um treino do histórico. O treino em andamento
     * só existe na tela de treino, nunca no histórico (nem para leitura, nem
     * para edição). Um treino já excluído nem chega aqui: o route model
     * binding ignora registros com `deleted_at` e responde 404.
     */
    private function ensureOwnedCompletedSession(Request $request, WorkoutSession $session): void
    {
        abort_if($session->user_id !== $request->user()->id, 403);
        abort_if($session->completed_at === null, 404);
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
     * @return array<int, array{exercise_id: int, name: string, equipment: string|null, volume: float, sets: array<int, array{id: int, set_number: int, weight: float|null, reps: int, is_warmup: bool}>}>
     */
    private function formatExercises(WorkoutSession $session, WorkoutHistoryService $service): array
    {
        return $service->exerciseBreakdown($session)
            ->map(fn (array $group) => [
                'exercise_id' => $group['exercise_id'],
                'name' => $group['exercise_name'],
                // O editor de série usa o mesmo passo de carga +/− do treino
                // em andamento, que depende do equipamento.
                'equipment' => $group['equipment'],
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
