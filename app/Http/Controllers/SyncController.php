<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncResourceMutationsRequest;
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

    /**
     * Recebe, em lote, as mutações de CRUD (programas e treinos) que o app
     * registrou offline (criação, edição, arquivamento, exclusão e
     * vínculo/desvínculo entre treino e programa) e as persiste na ordem
     * cronológica em que de fato ocorreram. A resposta inclui o ID real de
     * cada entidade criada, para que o dispositivo troque o ID temporário
     * (usado enquanto offline) pelo ID definitivo em silêncio.
     */
    public function resourceMutations(SyncResourceMutationsRequest $request, SyncService $service): JsonResponse
    {
        $results = $service->processResourceMutations($request->user(), $request->validated('actions'));

        return response()->json(['results' => $results]);
    }
}
