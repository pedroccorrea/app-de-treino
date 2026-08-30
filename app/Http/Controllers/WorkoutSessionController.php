<?php

namespace App\Http\Controllers;

use App\Http\Requests\LogSetRequest;
use App\Models\SetLog;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSession;
use App\Services\ProgressiveOverloadService;
use App\Services\WorkoutSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class WorkoutSessionController extends Controller
{
    public function start(Request $request, Workout $workout, WorkoutSessionService $service): RedirectResponse
    {
        abort_if($workout->user_id !== $request->user()->id, 403);

        $session = $service->startSession($request->user(), $workout);

        return redirect()
            ->route('workout-sessions.show', $session->id)
            ->with('success', 'Treino iniciado! Bom treino!');
    }

    public function show(Request $request, WorkoutSession $session, WorkoutSessionService $service): Response
    {
        abort_if($session->user_id !== $request->user()->id, 403);

        $session->load(['workout.exercises', 'setLogs']);

        $workout = $session->workout;

        $exercises = $workout ? $workout->exercises->map(function ($exercise) {
            return [
                'id' => $exercise->id,
                'name' => $exercise->name,
                'primary_muscle' => $exercise->primary_muscle_group->label(),
                'target_sets' => $exercise->pivot->target_sets ?? 3,
                'target_reps' => $exercise->pivot->target_reps ?? '10',
                'rest_seconds' => $exercise->pivot->rest_seconds ?? 60,
                'notes' => $exercise->pivot->notes,
            ];
        })->values()->all() : [];

        $exerciseIds = collect($exercises)->pluck('id')->all();
        $lastLogs = $service->getLastLogsForExercises($request->user(), $exerciseIds, $session->id);

        $setLogs = $session->setLogs->map(fn (SetLog $log) => [
            'id' => $log->id,
            'exercise_id' => $log->exercise_id,
            'set_number' => $log->set_number,
            'weight' => $log->weight !== null ? (float) $log->weight : null,
            'reps' => $log->reps,
            'rpe' => $log->rpe !== null ? (float) $log->rpe : null,
            'is_warmup' => (bool) $log->is_warmup,
            'notes' => $log->notes,
        ])->values()->all();

        return Inertia::render('WorkoutSessions/Active', [
            'session' => [
                'id' => $session->id,
                'started_at' => $session->started_at?->toISOString(),
                'completed_at' => $session->completed_at?->toISOString(),
                'notes' => $session->notes,
            ],
            'workout' => $workout ? [
                'id' => $workout->id,
                'name' => $workout->name,
                'description' => $workout->description,
            ] : null,
            'exercises' => $exercises,
            'setLogs' => $setLogs,
            'lastLogs' => (object) $lastLogs,
        ]);
    }

    /**
     * AI-backed overload suggestions are fetched by the frontend in the
     * background after the session screen has already rendered, so a slow
     * or failing AI call never blocks opening the workout session.
     * ProgressiveOverloadService already degrades to an empty collection on
     * any provider/JSON failure, so this only shapes the typed DTOs into a
     * plain JSON payload — no parsing happens here. The try/catch below is a
     * last-resort safety net: no matter what breaks (including something
     * outside the AI call itself), this endpoint must always answer with
     * HTTP 200 and never surface a fatal 500 in the browser console.
     */
    public function overloadSuggestions(Request $request, WorkoutSession $session, ProgressiveOverloadService $overloadService): JsonResponse
    {
        abort_if($session->user_id !== $request->user()->id, 403);

        $workout = $session->workout;

        try {
            return response()->json([
                'suggestions' => $workout
                    ? $this->safeOverloadSuggestions($overloadService, $request->user(), $workout)
                    : [],
            ]);
        } catch (Throwable $e) {
            Log::warning('Falha inesperada ao montar sugestões de sobrecarga progressiva. Retornando fallback suave.', [
                'session_id' => $session->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'fallback',
                'recommendations' => [],
            ]);
        }
    }

    /**
     * @return array<int, array{exercise_id: int, suggested_load: float, suggested_reps: int, previous_load: ?float, previous_reps: ?int, rationale: string, confidence: string}>
     */
    private function safeOverloadSuggestions(ProgressiveOverloadService $overloadService, User $user, Workout $workout): array
    {
        return $overloadService->analyzeWorkout($user, $workout)
            ->map(fn ($suggestion) => $suggestion->toArray())
            ->values()
            ->all();
    }

    public function logSet(LogSetRequest $request, WorkoutSession $session, WorkoutSessionService $service): RedirectResponse
    {
        abort_if($session->user_id !== $request->user()->id, 403);

        $service->logSet($session, $request->validated());

        return redirect()->back();
    }

    public function finish(Request $request, WorkoutSession $session, WorkoutSessionService $service): RedirectResponse
    {
        abort_if($session->user_id !== $request->user()->id, 403);

        $service->finishSession($session);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Treino finalizado com sucesso! Parabéns pelo esforço!');
    }
}
