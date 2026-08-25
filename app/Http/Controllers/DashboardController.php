<?php

namespace App\Http\Controllers;

use App\Enums\DayOfWeek;
use App\Models\Workout;
use App\Services\DashboardAnalyticsService;
use App\Services\WorkoutProgramService;
use App\Services\WorkoutService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, WorkoutService $workoutService, WorkoutProgramService $workoutProgramService, DashboardAnalyticsService $dashboardAnalyticsService): Response
    {
        $user = $request->user();
        $today = $workoutService->todayDayOfWeek();

        $activeWorkouts = $workoutService->getUserWorkouts($user, onlyActive: true);
        $activeProgram = $workoutProgramService->getActiveProgram($user);

        // "Treino de Hoje" só pode vir do programa atualmente ativo: uma ficha
        // arquivada junto de um programa antigo pode continuar com
        // `is_active = true` no nível de Workout, então o cruzamento com o
        // programa evita que ela "vaze" para o hero da Dashboard.
        $todayWorkout = $activeWorkouts
            ->filter(fn (Workout $workout) => $workout->workout_program_id === $activeProgram?->id)
            ->first(fn (Workout $workout) => ($workout->days_of_week ?? collect())->contains($today));

        return Inertia::render('Dashboard', [
            'todayWorkout' => $todayWorkout ? $this->formatTodayWorkout($todayWorkout) : null,
            'streak' => $dashboardAnalyticsService->calculateStreak($user),
            'activeWorkouts' => $activeWorkouts
                ->map(fn (Workout $workout) => $this->formatActiveWorkout($workout))
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTodayWorkout(Workout $workout): array
    {
        $muscleGroups = $workout->exercises
            ->map(fn ($exercise) => $exercise->primary_muscle_group->label())
            ->unique()
            ->values()
            ->all();

        return [
            'id' => $workout->id,
            'name' => $workout->name,
            'muscle_groups' => $muscleGroups,
            'exercises_count' => $workout->exercises->count(),
            'program_name' => $workout->workoutProgram?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatActiveWorkout(Workout $workout): array
    {
        $daysOfWeek = ($workout->days_of_week ?? collect())->sortBy(fn (DayOfWeek $day) => $day->value);

        return [
            'id' => $workout->id,
            'name' => $workout->name,
            'exercises_count' => $workout->exercises->count(),
            'days_of_week_labels' => $daysOfWeek->map(fn (DayOfWeek $day) => $day->shortLabel())->values()->all(),
        ];
    }
}
