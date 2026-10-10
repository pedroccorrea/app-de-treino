<?php

namespace App\Http\Controllers;

use App\Exceptions\AiUnavailableException;
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

            // Só a falha das empresas de IA vira "serviço indisponível"; o
            // resto (resposta que não vira ficha) é sobre a foto.
            $message = $e instanceof AiUnavailableException
                ? WorkoutScannerService::AI_UNAVAILABLE_MESSAGE
                : WorkoutScannerService::UNREADABLE_PHOTO_MESSAGE;

            return redirect()
                ->route('workouts.index')
                ->with('error', $message);
        }

        return redirect()
            ->route('workouts.show', $workout)
            ->with('success', 'Ficha escaneada e importada com sucesso!');
    }
}
