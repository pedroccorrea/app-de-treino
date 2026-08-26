<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttachWorkoutsRequest;
use App\Http\Requests\StoreWorkoutProgramRequest;
use App\Http\Requests\UpdateProgramRequest;
use App\Models\Workout;
use App\Models\WorkoutProgram;
use App\Services\WorkoutProgramService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkoutProgramController extends Controller
{
    public function index(Request $request, WorkoutProgramService $workoutProgramService): Response
    {
        $programs = $workoutProgramService->getUserPrograms($request->user());

        return Inertia::render('Programs/Index', [
            'programs' => $programs->map(fn (WorkoutProgram $program) => $this->formatProgram($program))->values()->all(),
        ]);
    }

    public function store(StoreWorkoutProgramRequest $request, WorkoutProgramService $workoutProgramService): RedirectResponse
    {
        $workoutProgramService->createProgram($request->user(), $request->validated());

        return redirect()->back()->with('success', 'Programa criado com sucesso!');
    }

    public function show(Request $request, WorkoutProgram $program, WorkoutProgramService $workoutProgramService): Response
    {
        abort_if($program->user_id !== $request->user()->id, 403);

        $program = $workoutProgramService->getProgramWithWorkouts($program);
        $availableWorkouts = $workoutProgramService->getUnlinkedWorkouts($program);

        return Inertia::render('Programs/Show', [
            'program' => $this->formatProgramDetail($program),
            'availableWorkouts' => $availableWorkouts->map(fn (Workout $workout) => [
                'id' => $workout->id,
                'name' => $workout->name,
                'exercises_count' => $workout->exercises_count,
            ])->values()->all(),
        ]);
    }

    public function update(UpdateProgramRequest $request, WorkoutProgram $program, WorkoutProgramService $workoutProgramService): RedirectResponse
    {
        abort_if($program->user_id !== $request->user()->id, 403);

        $workoutProgramService->updateProgram($program, $request->validated());

        return redirect()->back()->with('success', 'Programa atualizado com sucesso!');
    }

    public function activate(Request $request, WorkoutProgram $program, WorkoutProgramService $workoutProgramService): RedirectResponse
    {
        abort_if($program->user_id !== $request->user()->id, 403);

        $workoutProgramService->activateProgram($program);

        return redirect()->back()->with('success', 'Programa ativado com sucesso!');
    }

    public function archive(Request $request, WorkoutProgram $program, WorkoutProgramService $workoutProgramService): RedirectResponse
    {
        abort_if($program->user_id !== $request->user()->id, 403);

        $workoutProgramService->archiveProgram($program);

        return redirect()->back()->with('success', 'Programa arquivado com sucesso!');
    }

    public function destroy(Request $request, WorkoutProgram $program, WorkoutProgramService $workoutProgramService): RedirectResponse
    {
        abort_if($program->user_id !== $request->user()->id, 403);

        $workoutProgramService->deleteProgram($program);

        return redirect()->back()->with('success', 'Programa excluído com sucesso!');
    }

    public function attachWorkouts(AttachWorkoutsRequest $request, WorkoutProgram $program, WorkoutProgramService $workoutProgramService): RedirectResponse
    {
        abort_if($program->user_id !== $request->user()->id, 403);

        $workoutProgramService->attachWorkouts($program, $request->validated('workout_ids'));

        return redirect()->back()->with('success', 'Treinos vinculados ao programa com sucesso!');
    }

    public function detachWorkout(Request $request, WorkoutProgram $program, Workout $workout, WorkoutProgramService $workoutProgramService): RedirectResponse
    {
        abort_if($program->user_id !== $request->user()->id, 403);
        abort_if($workout->user_id !== $request->user()->id, 403);

        $workoutProgramService->detachWorkout($program, $workout);

        return redirect()->back()->with('success', 'Treino desvinculado do programa com sucesso!');
    }

    /**
     * @return array<string, mixed>
     */
    private function formatProgram(WorkoutProgram $program): array
    {
        return [
            'id' => $program->id,
            'name' => $program->name,
            'description' => $program->description,
            'is_active' => $program->is_active,
            'archived_at' => $program->archived_at?->format('d/m/Y'),
            'workouts_count' => $program->workouts_count,
            'workouts' => $program->workouts->map(fn ($workout) => [
                'id' => $workout->id,
                'name' => $workout->name,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatProgramDetail(WorkoutProgram $program): array
    {
        return [
            'id' => $program->id,
            'name' => $program->name,
            'description' => $program->description,
            'is_active' => $program->is_active,
            'workouts' => $program->workouts->map(fn (Workout $workout) => [
                'id' => $workout->id,
                'name' => $workout->name,
                'description' => $workout->description,
                'exercises_count' => $workout->exercises->count(),
            ])->values()->all(),
        ];
    }
}
