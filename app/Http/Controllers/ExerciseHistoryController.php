<?php

namespace App\Http\Controllers;

use App\Enums\HistoryRange;
use App\Models\Exercise;
use App\Models\SetLog;
use App\Models\WorkoutSession;
use App\Services\ExerciseHistoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ExerciseHistoryController extends Controller
{
    public function show(Exercise $exercise, Request $request, ExerciseHistoryService $service): Response
    {
        $user = $request->user();

        abort_if($exercise->user_id !== null && $exercise->user_id !== $user->id, 403);

        $range = HistoryRange::fromQuery($request->query('range'));

        $sessions = $service->getHistory($user, $exercise, $range);
        $personalRecord = $service->getPersonalRecord($user, $exercise);

        return Inertia::render('Exercises/History', [
            'exercise' => [
                'id' => $exercise->id,
                'name' => $exercise->name,
                'primary_muscle' => $exercise->primary_muscle_group->label(),
            ],
            'range' => $range->value,
            'ranges' => collect(HistoryRange::cases())->map(fn (HistoryRange $case) => [
                'value' => $case->value,
                'label' => $case->shortLabel(),
            ])->all(),
            'sessions' => $this->formatSessions($sessions),
            'volumeSeries' => $service->getVolumeSeries($user, $exercise, $range),
            'personalRecord' => $personalRecord,
        ]);
    }

    /**
     * @param  Collection<int, WorkoutSession>  $sessions
     * @return array<int, array{id: int, date: string, sets: array<int, array{set_number: int, weight: float|null, reps: int}>}>
     */
    private function formatSessions(Collection $sessions): array
    {
        return $sessions->map(fn (WorkoutSession $session) => [
            'id' => $session->id,
            'date' => $session->completed_at->toDateString(),
            'sets' => $session->setLogs->map(fn (SetLog $log) => [
                'set_number' => $log->set_number,
                'weight' => $log->weight !== null ? (float) $log->weight : null,
                'reps' => $log->reps,
            ])->values()->all(),
        ])->values()->all();
    }
}
