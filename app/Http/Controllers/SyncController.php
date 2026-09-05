<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncWorkoutSessionsRequest;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;

class SyncController extends Controller
{
    /**
     * Recebe, em lote, as mutações que o app registrou na fila de
     * sincronização (IndexedDB `sync_queue`) enquanto o dispositivo estava
     * offline — início de sessão, séries registradas e finalização de
     * treino — e as persiste na ordem cronológica em que de fato ocorreram.
     */
    public function workoutSessions(SyncWorkoutSessionsRequest $request, SyncService $service): JsonResponse
    {
        $results = $service->processWorkoutSessionActions($request->user(), $request->validated('actions'));

        return response()->json(['results' => $results]);
    }
}
