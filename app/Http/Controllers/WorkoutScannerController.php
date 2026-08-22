<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScanWorkoutRequest;
use App\Services\WorkoutScannerService;
use Illuminate\Http\RedirectResponse;

class WorkoutScannerController extends Controller
{
    public function store(ScanWorkoutRequest $request, WorkoutScannerService $workoutScannerService): RedirectResponse
    {
        $workout = $workoutScannerService->scanAndImport($request->file('image'), $request->user());

        return redirect()
            ->route('workouts.show', $workout)
            ->with('success', 'Ficha escaneada e importada com sucesso!');
    }
}
