<?php

namespace App\Http\Controllers;

use App\Enums\DayOfWeek;
use App\Http\Requests\StoreWorkoutRequest;
use App\Http\Requests\UpdateWorkoutRequest;
use App\Models\Workout;
use App\Models\WorkoutProgram;
use App\Services\WorkoutProgramService;
use App\Services\WorkoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkoutController extends Controller
{
    public function index(Request $request, WorkoutService $workoutService, WorkoutProgramService $workoutProgramService): Response
    {
        $user = $request->user();
        $today = $workoutService->todayDayOfWeek();

        $workouts = $workoutService->getUserWorkouts($user)
            ->map(fn (Workout $workout) => $this->formatWorkout($workout, $today));

        $programs = $workoutProgramService->getUserPrograms($user);
        $activeProgram = $programs->firstWhere('is_active', true);
        $archivedPrograms = $programs->where('is_active', false)->values();

        return Inertia::render('Workouts/Index', [
            'workouts' => $workouts,
            'exercisesCatalog' => $workoutService->getAvailableExercises(),
            'todayDayOfWeek' => $today->value,
            'todayDayOfWeekLabel' => $today->label(),
            'activeProgram' => $activeProgram ? $this->formatActiveProgram($activeProgram) : null,
            'archivedPrograms' => $archivedPrograms->map(fn (WorkoutProgram $program) => $this->formatArchivedProgram($program))->values()->all(),
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

    public function toggleArchive(Request $request, Workout $workout, WorkoutService $workoutService): RedirectResponse
    {
        abort_if($workout->user_id !== $request->user()->id, 403);

        $workoutService->toggleArchive($workout);

        return redirect()->back()->with(
            'success',
            $workout->is_active ? 'Treino reativado com sucesso!' : 'Treino arquivado com sucesso!'
        );
    }

    public function destroy(Request $request, Workout $workout, WorkoutService $workoutService): RedirectResponse
    {
        abort_if($workout->user_id !== $request->user()->id, 403);

        $workoutService->destroy($workout);

        return redirect()
            ->route('workouts.index')
            ->with('success', 'Treino excluído com sucesso!');
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
            'is_active' => $workout->is_active,
            'workout_program_id' => $workout->workout_program_id,
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

    /**
     * @return array<string, mixed>
     */
    private function formatActiveProgram(WorkoutProgram $program): array
    {
        return [
            'id' => $program->id,
            'name' => $program->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatArchivedProgram(WorkoutProgram $program): array
    {
        return [
            'id' => $program->id,
            'name' => $program->name,
            'archived_at' => $program->archived_at?->format('d/m/Y'),
            'workouts' => $program->workouts->map(fn (Workout $workout) => [
                'id' => $workout->id,
                'name' => $workout->name,
            ])->values()->all(),
        ];
    }
}
