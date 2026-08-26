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

        $activeProgram = $workoutProgramService->getActiveProgram($user);

        return Inertia::render('Workouts/Index', [
            'workouts' => $workouts,
            'exercisesCatalog' => $workoutService->getAvailableExercises(),
            'todayDayOfWeek' => $today->value,
            'todayDayOfWeekLabel' => $today->label(),
            'activeProgram' => $activeProgram ? $this->formatActiveProgram($activeProgram) : null,
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
        $workout = $workoutService->createWorkout($request->user(), $request->validated());

        if ($request->filled('workout_program_id')) {
            $returnTo = $this->safeRedirectTarget(
                $request->input('return_to'),
                route('programs.show', $workout->workout_program_id)
            );

            return redirect()
                ->route('workouts.edit', ['workout' => $workout->id, 'return_to' => $returnTo])
                ->with('success', 'Ficha criada! Agora adicione os exercícios.');
        }

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
            ->to($this->safeRedirectTarget($request->query('return_to'), route('workouts.show', $workout)))
            ->with('success', 'Treino atualizado com sucesso!');
    }

    /**
     * Several screens (the workouts list, a workout's own page, a program's
     * page) can open the create/edit flow, and pass back a `return_to`
     * path/URL so the user lands where they came from instead of always on
     * a fixed page. Only same-app targets (relative paths or URLs under the
     * app's own domain) are honored to avoid an open redirect.
     */
    private function safeRedirectTarget(?string $returnTo, string $fallback): string
    {
        if (! $returnTo) {
            return $fallback;
        }

        if (str_starts_with($returnTo, '/') && ! str_starts_with($returnTo, '//')) {
            return $returnTo;
        }

        if (str_starts_with($returnTo, config('app.url'))) {
            return $returnTo;
        }

        return $fallback;
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
}
