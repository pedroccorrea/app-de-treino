<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkoutRequest;
use App\Models\Workout;
use App\Services\WorkoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkoutController extends Controller
{
    public function index(Request $request, WorkoutService $workoutService): Response
    {
        $workouts = $workoutService->getUserWorkouts($request->user())
            ->map(fn (Workout $workout) => $this->formatWorkout($workout));

        return Inertia::render('Workouts/Index', [
            'workouts' => $workouts,
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

    /**
     * @return array<string, mixed>
     */
    private function formatWorkout(Workout $workout): array
    {
        $muscleGroups = collect();

        foreach ($workout->exercises as $exercise) {
            $muscleGroups->push($exercise->primary_muscle_group->label());

            foreach ($exercise->secondary_muscle_groups ?? [] as $secondary) {
                $muscleGroups->push($secondary->label());
            }
        }

        return [
            'id' => $workout->id,
            'name' => $workout->name,
            'description' => $workout->description,
            'exercises_count' => $workout->exercises->count(),
            'muscle_groups' => $muscleGroups->unique()->values()->all(),
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
