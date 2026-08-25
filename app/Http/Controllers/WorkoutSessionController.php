<?php

namespace App\Http\Controllers;

use App\Exceptions\GeminiException;
use App\Http\Requests\LogSetRequest;
use App\Models\SetLog;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSession;
use App\Services\ProgressiveOverloadService;
use App\Services\WorkoutSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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

    public function show(Request $request, WorkoutSession $session, WorkoutSessionService $service, ProgressiveOverloadService $overloadService): Response
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
            'overloadSuggestions' => $workout
                ? $this->safeOverloadSuggestions($overloadService, $request->user(), $workout)
                : [],
        ]);
    }

    /**
     * The overload suggestions are the only AI-backed part of the active
     * session screen; a Gemini failure shouldn't prevent the workout from
     * being logged, so it degrades to "no suggestions" instead of a fatal
     * error. Skipped entirely when there's no completed-session history yet,
     * since the AI has nothing to base a suggestion on.
     *
     * @return array<int, array{exercise_name: string, current_load: string, suggested_load: string, suggested_reps: string, rationale: string}>
     */
    private function safeOverloadSuggestions(ProgressiveOverloadService $overloadService, User $user, Workout $workout): array
    {
        $hasHistory = $workout->workoutSessions()
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->exists();

        if (! $hasHistory) {
            return [];
        }

        try {
            return $overloadService->analyzeWorkout($user, $workout)['recommendations'] ?? [];
        } catch (GeminiException) {
            return [];
        }
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
