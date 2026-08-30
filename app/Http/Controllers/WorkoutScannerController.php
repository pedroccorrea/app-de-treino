<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScanWorkoutRequest;
use App\Services\WorkoutScannerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

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
        } catch (\Throwable $e) {
            Log::warning('Falha ao escanear e importar ficha de treino.', ['message' => $e->getMessage()]);

            return redirect()
                ->route('workouts.index')
                ->with('error', 'Não foi possível ler esta imagem. Tente tirar uma foto mais nítida ou aproximada.');
        }

        return redirect()
            ->route('workouts.show', $workout)
            ->with('success', 'Ficha escaneada e importada com sucesso!');
    }
}
