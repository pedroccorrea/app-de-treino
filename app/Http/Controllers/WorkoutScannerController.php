<?php

namespace App\Http\Controllers;

use App\Exceptions\GeminiException;
use App\Http\Requests\ScanWorkoutRequest;
use App\Services\WorkoutScannerService;
use Illuminate\Http\RedirectResponse;

class WorkoutScannerController extends Controller
{
    public function store(ScanWorkoutRequest $request, WorkoutScannerService $workoutScannerService): RedirectResponse
    {
        try {
            $workout = $workoutScannerService->scanAndImport(
                $request->file('image'),
                $request->user(),
                $request->validated('workout_program_id'),
            );
        } catch (GeminiException $e) {
            return redirect()
                ->route('workouts.index')
                ->with('error', 'Não foi possível escanear a ficha: '.$e->getMessage());
        }

        return redirect()
            ->route('workouts.show', $workout)
            ->with('success', 'Ficha escaneada e importada com sucesso!');
    }
}
