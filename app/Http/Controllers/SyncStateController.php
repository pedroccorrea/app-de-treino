<?php

namespace App\Http\Controllers;

use App\Enums\DayOfWeek;
use App\Models\Workout;
use App\Models\WorkoutProgram;
use App\Services\WorkoutProgramService;
use App\Services\WorkoutService;
use App\Services\WorkoutSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SyncStateController extends Controller
{
    /**
     * Devolve, em uma única resposta JSON, todo o estado do usuário
     * necessário para o app funcionar totalmente offline: programas,
     * treinos (com exercícios), o catálogo global de exercícios e o
     * histórico recente ("última vez que fiz") por exercício.
     *
     * O frontend chama este endpoint ao carregar a aplicação online (ver
     * `resources/js/Utils/syncState.js`) e sobrescreve o IndexedDB com o
     * resultado — é a "foto" completa que o mini-servidor do Service Worker
     * (`resources/pwa/sw.js`) usa depois para montar páginas Inertia válidas
     * mesmo sem nenhuma requisição de rede ter funcionado antes.
     */
    public function index(
        Request $request,
        WorkoutService $workoutService,
        WorkoutProgramService $workoutProgramService,
        WorkoutSessionService $workoutSessionService,
    ): JsonResponse {
        $user = $request->user();
        $today = $workoutService->todayDayOfWeek();

        $workouts = $workoutService->getUserWorkouts($user);
        $programs = $workoutProgramService->getUserPrograms($user);
        $programs->load('workouts.exercises');

        $exerciseIds = $workouts
            ->flatMap(fn (Workout $workout) => $workout->exercises->pluck('id'))
            ->unique()
            ->values()
            ->all();

        $lastLogs = $workoutSessionService->getLastLogsForExercises($user, $exerciseIds);

        return response()->json([
            'version' => Inertia::getVersion(),
            'programs' => $programs->map(fn (WorkoutProgram $program) => $this->formatProgram($program))->values()->all(),
            'workouts' => $workouts->map(fn (Workout $workout) => $this->formatWorkout($workout, $today))->values()->all(),
            'exercisesCatalog' => $workoutService->getAvailableExercises(),
            'lastLogs' => (object) $lastLogs,
            'syncedAt' => now()->toISOString(),
        ]);
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
            'workouts_count' => $program->workouts_count ?? $program->workouts->count(),
            'workouts' => $program->workouts->map(fn (Workout $workout) => [
                'id' => $workout->id,
                'name' => $workout->name,
                'description' => $workout->description,
                'exercises_count' => $workout->exercises->count(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatWorkout(Workout $workout, DayOfWeek $today): array
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
            'program_ids' => $workout->programs->pluck('id')->all(),
            'exercises_count' => $workout->exercises->count(),
            'muscle_groups' => $muscleGroups->unique()->values()->all(),
            'days_of_week' => $daysOfWeek->map(fn (DayOfWeek $day) => $day->value)->values()->all(),
            'days_of_week_labels' => $daysOfWeek->map(fn (DayOfWeek $day) => $day->shortLabel())->values()->all(),
            'is_today' => $daysOfWeek->contains($today),
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
