<?php

namespace App\Http\Controllers;

use App\Enums\DayOfWeek;
use App\Http\Requests\StoreWorkoutRequest;
use App\Http\Requests\UpdateWorkoutRequest;
use App\Models\Workout;
use App\Services\ProgressiveOverloadService;
use App\Services\WorkoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkoutController extends Controller
{
    public function index(Request $request, WorkoutService $workoutService): Response
    {
        $today = $workoutService->todayDayOfWeek();

        $workouts = $workoutService->getUserWorkouts($request->user())
            ->map(fn (Workout $workout) => $this->formatWorkout($workout, $today));

        return Inertia::render('Workouts/Index', [
            'workouts' => $workouts,
            'exercisesCatalog' => $workoutService->getAvailableExercises(),
            'todayDayOfWeek' => $today->value,
            'todayDayOfWeekLabel' => $today->label(),
        ]);
    }

    public function create(WorkoutService $workoutService): Response
    {
        return Inertia::render('Workouts/Create', [
            'exercisesCatalog' => $workoutService->getAvailableExercises(),
        ]);
    }

    public function show(Request $request, Workout $workout): Response
    {
        abort_if($workout->user_id !== $request->user()->id, 403);

        $workout->load('exercises');

        return Inertia::render('Workouts/Show', [
            'workout' => $this->formatWorkout($workout),
        ]);
    }

    public function store(StoreWorkoutRequest $request, WorkoutService $workoutService): RedirectResponse
    {
        $workoutService->createWorkout($request->user(), $request->validated());

        return redirect()
            ->route('workouts.index')
            ->with('success', 'Treino criado com sucesso!');
    }

    public function edit(Request $request, Workout $workout, WorkoutService $workoutService): Response
    {
        abort_if($workout->user_id !== $request->user()->id, 403);

        $workout->load('exercises');

        return Inertia::render('Workouts/Edit', [
            'workout' => $this->formatWorkout($workout),
            'exercisesCatalog' => $workoutService->getAvailableExercises(),
        ]);
    }

    public function update(UpdateWorkoutRequest $request, Workout $workout, WorkoutService $workoutService): RedirectResponse
    {
        abort_if($workout->user_id !== $request->user()->id, 403);

        $workoutService->updateWorkout($workout, $request->validated());

        return redirect()
            ->to($this->safeRedirectTarget($request->query('redirect_to'), $workout))
            ->with('success', 'Treino atualizado com sucesso!');
    }

    /**
     * The edit screen may be opened from different pages (the workouts list,
     * the workout's own page). It passes back a `redirect_to` path so the
     * user lands where they came from instead of always on the show page.
     * Only same-app relative paths are honored to avoid an open redirect.
     */
    private function safeRedirectTarget(?string $redirectTo, Workout $workout): string
    {
        if ($redirectTo && str_starts_with($redirectTo, '/') && ! str_starts_with($redirectTo, '//')) {
            return $redirectTo;
        }

        return route('workouts.show', $workout);
    }

    public function overloadSuggestions(Request $request, Workout $workout, ProgressiveOverloadService $progressiveOverloadService): JsonResponse
    {
        abort_if($workout->user_id !== $request->user()->id, 403);

        return response()->json(
            $progressiveOverloadService->analyzeWorkout($request->user(), $workout)
        );
    }

    public function reorder(Request $request, Workout $workout, WorkoutService $workoutService): RedirectResponse
    {
        abort_if($workout->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'exercise_ids' => ['required', 'array'],
            'exercise_ids.*' => ['required', 'integer'],
        ]);

        $workoutService->reorderExercises($workout, $validated['exercise_ids']);

        return redirect()->back();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatWorkout(Workout $workout, ?DayOfWeek $today = null): array
    {
        $muscleGroups = collect();

        foreach ($workout->exercises as $exercise) {
            $muscleGroups->push($exercise->primary_muscle_group->label());

            foreach ($exercise->secondary_muscle_groups ?? [] as $secondary) {
                $muscleGroups->push($secondary->label());
            }
        }

        $daysOfWeek = ($workout->days_of_week ?? collect())->sortBy(fn (DayOfWeek $day) => $day->value);

        return [
            'id' => $workout->id,
            'name' => $workout->name,
            'description' => $workout->description,
            'exercises_count' => $workout->exercises->count(),
            'muscle_groups' => $muscleGroups->unique()->values()->all(),
            'days_of_week' => $daysOfWeek->map(fn (DayOfWeek $day) => $day->value)->values()->all(),
            'days_of_week_labels' => $daysOfWeek->map(fn (DayOfWeek $day) => $day->shortLabel())->values()->all(),
            'is_today' => $today !== null && $daysOfWeek->contains($today),
            'exercises' => $workout->exercises->map(fn ($exercise) => [
                'id' => $exercise->id,
                'name' => $exercise->name,
                'primary_muscle' => $exercise->primary_muscle_group->label(),
                'target_sets' => $exercise->pivot->target_sets,
                'target_reps' => $exercise->pivot->target_reps,
                'order' => $exercise->pivot->order,
            ])->values()->all(),
        ];
    }
}
