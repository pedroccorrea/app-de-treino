<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkoutProgramRequest;
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
}
