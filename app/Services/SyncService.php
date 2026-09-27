<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutProgram;
use App\Models\WorkoutSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class SyncService
{
    public function __construct(
        private WorkoutSessionService $workoutSessionService,
        private WorkoutService $workoutService,
        private WorkoutProgramService $workoutProgramService,
    ) {}

    /**
     * Processa, em uma única transação e em ordem cronológica (pelo
     * `occurred_at` registrado no dispositivo — não pela ordem de chegada na
     * rede), o lote de mutações que o app registrou na fila offline
     * (`sync_queue` do IndexedDB) enquanto o usuário estava sem internet.
     *
     * `client_session_id` agrupa as ações pertencentes à mesma sessão de
     * treino: a primeira `start_session` de cada grupo cria a sessão real no
     * banco, preservando o instante em que ela de fato começou offline; as
     * ações seguintes (`log_set`, `finish_session`) resolvem a sessão real
     * pelo mesmo `client_session_id`, ou por um `session_id` já existente
     * quando a sessão foi iniciada online e a conexão caiu no meio do treino.
     *
     * Todo o processamento é idempotente: reenviar o mesmo lote (cenário
     * normal em reconexões instáveis, quando o app não recebeu a confirmação
     * a tempo de esvaziar a fila) nunca duplica uma `WorkoutSession` — o
     * `client_session_id` é persistido como `client_uuid` e usado para
     * localizar a sessão já criada em vez de criar uma nova.
     *
     * Cada ação é processada isoladamente: se UMA não puder ser resolvida
     * (ex.: referencia uma sessão que nunca existiu, ou ainda não chegou a
     * este servidor), ela simplesmente fica de fora de `results` — o
     * dispositivo a mantém na fila local para tentar de novo — e o
     * processamento CONTINUA para as ações seguintes. Sem isso, uma única
     * ação permanentemente irresolúvel travada na fila bloquearia para
     * sempre TODAS as outras ações do mesmo lote (o lote inteiro rodava
     * dentro de uma única transação que abortava por completo), inclusive
     * ações novas e perfeitamente válidas enfileiradas depois dela.
     *
     * @param  array<int, array<string, mixed>>  $actions
     * @return array<int, array{uuid: string, client_session_id: string, session_id: ?int, status: string}>
     */
    public function processWorkoutSessionActions(User $user, array $actions): array
    {
        $ordered = collect($actions)
            ->values()
            ->map(fn (array $action, int $index) => $action + ['_order' => $index])
            ->sort(function (array $a, array $b) {
                $diff = Carbon::parse($a['occurred_at'])->getPreciseTimestamp(6)
                    <=> Carbon::parse($b['occurred_at'])->getPreciseTimestamp(6);

                return $diff !== 0 ? $diff : $a['_order'] <=> $b['_order'];
            })
            ->values();

        return DB::transaction(function () use ($user, $ordered) {
            /** @var array<string, WorkoutSession> $sessionsByClientId */
            $sessionsByClientId = [];
            $results = [];

            foreach ($ordered as $action) {
                $clientSessionId = $action['client_session_id'];
                $occurredAt = Carbon::parse($action['occurred_at']);

                // Cada ação roda no seu próprio savepoint (transação
                // aninhada): se ela falhar no meio do caminho, só o que ELA
                // escreveu é desfeito — as ações anteriores do lote seguem
                // confirmadas.
                try {
                    $session = DB::transaction(fn () => match ($action['type']) {
                        'start_session' => $this->startSession($user, $action, $occurredAt),
                        'log_set' => $this->logSet(
                            $user,
                            $this->resolveSession($user, $sessionsByClientId, $clientSessionId, $action['session_id'] ?? null),
                            $action,
                            $occurredAt
                        ),
                        'finish_session' => $this->finishSession(
                            $this->resolveSession($user, $sessionsByClientId, $clientSessionId, $action['session_id'] ?? null),
                            $occurredAt
                        ),
                    });
                } catch (Throwable $e) {
                    Log::warning('[sync] Ação de sessão de treino não pôde ser sincronizada agora; permanece na fila do dispositivo.', [
                        'user_id' => $user->id,
                        'action_uuid' => $action['uuid'],
                        'action_type' => $action['type'],
                        'client_session_id' => $clientSessionId,
                        'workout_id' => $action['workout_id'] ?? null,
                        'message' => $e->getMessage(),
                    ]);

                    continue;
                }

                $sessionsByClientId[$clientSessionId] = $session;

                $results[] = [
                    'uuid' => $action['uuid'],
                    'client_session_id' => $clientSessionId,
                    'session_id' => $session->id,
                    'status' => 'synced',
                ];
            }

            return $results;
        });
    }

    /**
     * Cria a sessão real referente a este `start_session` offline — ou, se
     * este mesmo lote já tiver sido sincronizado antes (reenvio por
     * reconexão instável), devolve a sessão já criada na tentativa anterior
     * em vez de criar uma duplicata. `client_uuid` é o que torna essa busca
     * possível: é o mesmo `client_session_id` gerado no dispositivo antes de
     * a sessão existir no banco.
     */
    private function startSession(User $user, array $action, Carbon $occurredAt): WorkoutSession
    {
        $existing = WorkoutSession::query()
            ->where('user_id', $user->id)
            ->where('client_uuid', $action['client_session_id'])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $workout = $this->resolveOwnedWorkoutForSession($user, $action['workout_id']);

        $this->workoutSessionService->closeOpenSessionsForUser($user);

        $session = $user->workoutSessions()->create([
            'workout_id' => $workout->id,
            'client_uuid' => $action['client_session_id'],
            'started_at' => $occurredAt,
            'completed_at' => null,
        ]);

        $session->forceFill(['created_at' => $occurredAt, 'updated_at' => $occurredAt])->save();

        return $session;
    }

    /**
     * Esta é a ÚNICA barreira de posse do `workout_id` de um `start_session`
     * (a request só valida o formato). Escopo estrito por usuário via
     * relação, não uma query solta em `Workout`: uma ficha de outro usuário,
     * excluída ou órfã nunca é aceita — ela só isola esta ação, sem derrubar
     * o lote. `tmp_<client_uuid>` é a ficha criada offline e treinada na
     * mesma janela sem internet: ela já foi criada pelo lote de mutações de
     * recurso, sincronizado antes deste.
     */
    private function resolveOwnedWorkoutForSession(User $user, int|string $rawWorkoutId): Workout
    {
        $workout = is_string($rawWorkoutId) && str_starts_with($rawWorkoutId, 'tmp_')
            ? $user->workouts()->where('client_uuid', substr($rawWorkoutId, 4))->first()
            : $user->workouts()->whereKey($rawWorkoutId)->first();

        if ($workout === null) {
            throw ValidationException::withMessages([
                'workout_id' => 'A ficha informada para esta sessão de treino não foi encontrada.',
            ]);
        }

        return $workout;
    }

    private function logSet(User $user, ?WorkoutSession $session, array $action, Carbon $occurredAt): WorkoutSession
    {
        $session = $this->requireSession($session);

        // Mesma barreira tenant-scoped que a request fazia antes: só
        // exercícios do catálogo global ou criados por este usuário.
        if (! Exercise::query()->forUser($user)->whereKey($action['exercise_id'])->exists()) {
            throw ValidationException::withMessages([
                'exercise_id' => 'O exercício informado para esta série não foi encontrado.',
            ]);
        }

        $log = $session->setLogs()->updateOrCreate(
            [
                'exercise_id' => $action['exercise_id'],
                'set_number' => $action['set_number'],
            ],
            [
                'client_uuid' => $action['client_set_id'] ?? null,
                'weight' => $action['weight'] ?? null,
                'reps' => $action['reps'],
                'rpe' => $action['rpe'] ?? null,
                'is_warmup' => (bool) ($action['is_warmup'] ?? false),
                'notes' => $action['notes'] ?? null,
            ]
        );

        $log->forceFill(['created_at' => $occurredAt, 'updated_at' => $occurredAt])->save();

        return $session;
    }

    /**
     * Finaliza a sessão e encerra qualquer outra sessão fantasma do mesmo
     * usuário, igual a `WorkoutSessionService::finishSession`. Reenviar o
     * mesmo `finish_session` (lote retransmitido) é inofensivo: se a sessão
     * já estiver concluída, a atualização é pulada em vez de reaplicada.
     */
    private function finishSession(?WorkoutSession $session, Carbon $occurredAt): WorkoutSession
    {
        $session = $this->requireSession($session);

        if ($session->completed_at === null) {
            $session->update(['completed_at' => $occurredAt]);
            $session->forceFill(['updated_at' => $occurredAt])->save();

            $this->workoutSessionService->closeOpenSessionsForUser($session->user, $session->id);
        }

        return $session;
    }

    /**
     * Encontra a `WorkoutSession` real à qual um `log_set`/`finish_session`
     * pertence, em três camadas — nesta ordem:
     *
     *   1. O mapa do lote atual (`start_session` processado agora há pouco);
     *   2. O banco, por `client_uuid` — a sessão já foi criada por um lote
     *      ANTERIOR. É o caso decisivo: a conexão volta no meio do treino, a
     *      fila é sincronizada e esvaziada, o usuário continua registrando
     *      séries e o próximo lote chega sem nenhum `start_session` dentro.
     *      Sem esta camada, esse lote era rejeitado com 422 ("Nenhuma sessão
     *      de treino correspondente…") e as séries ficavam presas na fila
     *      para sempre;
     *   3. O `session_id` real enviado pelo dispositivo (sessão iniciada
     *      online), com o próprio rótulo `session-<id>` como último recurso
     *      caso o `session_id` não tenha vindo no payload.
     *
     * @param  array<string, WorkoutSession>  $sessionsByClientId
     */
    private function resolveSession(User $user, array $sessionsByClientId, string $clientSessionId, ?int $sessionId): ?WorkoutSession
    {
        if (isset($sessionsByClientId[$clientSessionId])) {
            return $sessionsByClientId[$clientSessionId];
        }

        $byClientUuid = WorkoutSession::query()
            ->where('user_id', $user->id)
            ->where('client_uuid', $clientSessionId)
            ->first();

        if ($byClientUuid !== null) {
            return $byClientUuid;
        }

        $session = $this->findOwnedSession($user, $sessionId);

        if ($session !== null) {
            return $session;
        }

        if (preg_match('/^session-(\d+)$/', $clientSessionId, $matches) === 1) {
            return $this->findOwnedSession($user, (int) $matches[1]);
        }

        return null;
    }

    private function findOwnedSession(User $user, ?int $sessionId): ?WorkoutSession
    {
        if ($sessionId === null) {
            return null;
        }

        return WorkoutSession::query()
            ->where('id', $sessionId)
            ->where('user_id', $user->id)
            ->first();
    }

    private function requireSession(?WorkoutSession $session): WorkoutSession
    {
        if ($session === null) {
            throw ValidationException::withMessages([
                'actions' => 'Nenhuma sessão de treino correspondente foi encontrada para sincronizar esta ação.',
            ]);
        }

        return $session;
    }

    /**
     * Processa, em uma única transação e em ordem cronológica, o lote de
     * mutações de CRUD (programas e treinos) registradas offline na fila de
     * sincronização (IndexedDB `sync_queue`).
     *
     * Referências entre ações do mesmo lote (ex.: uma ficha criada offline e
     * já anexada, na mesma sessão offline, a um programa também criado
     * offline) usam uma referência temporária `tmp_<client_uuid>` em vez de
     * um ID real de banco — `$createdProgramsByUuid`/`$createdWorkoutsByUuid`
     * é o que permite resolver essas referências à medida que cada `create_*`
     * é processado, mesmo que a criação da entidade referenciada só exista a
     * partir desta mesma chamada.
     *
     * Assim como em `processWorkoutSessionActions`, `client_uuid` é persistido
     * no registro criado e usado para detectar reenvios do mesmo lote (ex.:
     * reconexões instáveis) sem duplicar o programa/treino.
     *
     * Cada ação é processada isoladamente, pela mesma razão descrita em
     * `processWorkoutSessionActions`: uma ação que não pode ser resolvida
     * agora (ex.: referencia via `tmp_<uuid>` um treino cujo `create_workout`
     * ainda não chegou a este servidor) fica de fora de `results` — o
     * dispositivo a mantém na fila — SEM impedir que as demais ações do
     * mesmo lote, incluindo criações novas totalmente independentes, sejam
     * sincronizadas normalmente.
     *
     * @param  array<int, array<string, mixed>>  $actions
     * @return array<int, array{uuid: string, type: string, client_uuid: ?string, id: ?int, status: string}>
     */
    public function processResourceMutations(User $user, array $actions): array
    {
        $ordered = collect($actions)
            ->values()
            ->map(fn (array $action, int $index) => $action + ['_order' => $index])
            ->sort(function (array $a, array $b) {
                $diff = Carbon::parse($a['occurred_at'])->getPreciseTimestamp(6)
                    <=> Carbon::parse($b['occurred_at'])->getPreciseTimestamp(6);

                return $diff !== 0 ? $diff : $a['_order'] <=> $b['_order'];
            })
            ->values();

        return DB::transaction(function () use ($user, $ordered) {
            /** @var array<string, WorkoutProgram> $createdProgramsByUuid */
            $createdProgramsByUuid = [];
            /** @var array<string, Workout> $createdWorkoutsByUuid */
            $createdWorkoutsByUuid = [];
            $results = [];

            foreach ($ordered as $action) {
                try {
                    $model = match ($action['type']) {
                        'create_program' => $this->createProgramAction($user, $action, $createdProgramsByUuid),
                        'update_program' => $this->workoutProgramService->updateProgram(
                            $this->resolveProgram($user, $action['program_id'], $createdProgramsByUuid),
                            $action
                        ),
                        'activate_program' => $this->workoutProgramService->activateProgram(
                            $this->resolveProgram($user, $action['program_id'], $createdProgramsByUuid)
                        ),
                        'archive_program' => $this->workoutProgramService->archiveProgram(
                            $this->resolveProgram($user, $action['program_id'], $createdProgramsByUuid)
                        ),
                        'delete_program' => $this->deleteProgramAction($user, $action, $createdProgramsByUuid),
                        'create_workout' => $this->createWorkoutAction($user, $action, $createdProgramsByUuid, $createdWorkoutsByUuid),
                        'update_workout' => $this->workoutService->updateWorkout(
                            $this->resolveWorkout($user, $action['workout_id'], $createdWorkoutsByUuid),
                            $action
                        ),
                        'archive_workout' => $this->workoutService->toggleArchive(
                            $this->resolveWorkout($user, $action['workout_id'], $createdWorkoutsByUuid)
                        ),
                        'delete_workout' => $this->deleteWorkoutAction($user, $action, $createdWorkoutsByUuid),
                        'attach_workout' => $this->attachWorkoutAction($user, $action, $createdProgramsByUuid, $createdWorkoutsByUuid),
                        'detach_workout' => $this->detachWorkoutAction($user, $action, $createdProgramsByUuid, $createdWorkoutsByUuid),
                    };
                } catch (Throwable $e) {
                    Log::warning('[sync] Mutação offline não pôde ser sincronizada agora; permanece na fila do dispositivo.', [
                        'user_id' => $user->id,
                        'action_type' => $action['type'],
                        'client_uuid' => $action['client_uuid'] ?? null,
                        'message' => $e->getMessage(),
                    ]);

                    continue;
                }

                $results[] = [
                    'uuid' => $action['uuid'],
                    'type' => $action['type'],
                    'client_uuid' => $action['client_uuid'] ?? null,
                    'id' => $model?->id,
                    'status' => 'synced',
                ];
            }

            return $results;
        });
    }

    private function createProgramAction(User $user, array $action, array &$createdProgramsByUuid): WorkoutProgram
    {
        $existing = WorkoutProgram::query()
            ->where('user_id', $user->id)
            ->where('client_uuid', $action['client_uuid'])
            ->first();

        if ($existing !== null) {
            return $createdProgramsByUuid[$action['client_uuid']] = $existing;
        }

        $program = $this->workoutProgramService->createProgram($user, $action);
        $program->forceFill(['client_uuid' => $action['client_uuid']])->save();

        return $createdProgramsByUuid[$action['client_uuid']] = $program;
    }

    private function deleteProgramAction(User $user, array $action, array &$createdProgramsByUuid): WorkoutProgram
    {
        $program = $this->resolveProgram($user, $action['program_id'], $createdProgramsByUuid);
        $this->workoutProgramService->deleteProgram($program);

        return $program;
    }

    private function createWorkoutAction(User $user, array $action, array &$createdProgramsByUuid, array &$createdWorkoutsByUuid): Workout
    {
        $existing = Workout::query()
            ->where('user_id', $user->id)
            ->where('client_uuid', $action['client_uuid'])
            ->first();

        if ($existing !== null) {
            return $createdWorkoutsByUuid[$action['client_uuid']] = $existing;
        }

        $data = $action;
        $data['workout_program_id'] = empty($action['workout_program_id'])
            ? null
            : $this->resolveProgram($user, $action['workout_program_id'], $createdProgramsByUuid)->id;

        $workout = $this->workoutService->createWorkout($user, $data);
        $workout->forceFill(['client_uuid' => $action['client_uuid']])->save();

        return $createdWorkoutsByUuid[$action['client_uuid']] = $workout;
    }

    private function deleteWorkoutAction(User $user, array $action, array &$createdWorkoutsByUuid): Workout
    {
        $workout = $this->resolveWorkout($user, $action['workout_id'], $createdWorkoutsByUuid);
        $this->workoutService->destroy($workout);

        return $workout;
    }

    private function attachWorkoutAction(User $user, array $action, array &$createdProgramsByUuid, array &$createdWorkoutsByUuid): WorkoutProgram
    {
        $program = $this->resolveProgram($user, $action['program_id'], $createdProgramsByUuid);
        $workout = $this->resolveWorkout($user, $action['workout_id'], $createdWorkoutsByUuid);

        $this->workoutProgramService->attachWorkouts($program, [$workout->id]);

        return $program;
    }

    private function detachWorkoutAction(User $user, array $action, array &$createdProgramsByUuid, array &$createdWorkoutsByUuid): WorkoutProgram
    {
        $program = $this->resolveProgram($user, $action['program_id'], $createdProgramsByUuid);
        $workout = $this->resolveWorkout($user, $action['workout_id'], $createdWorkoutsByUuid);

        $this->workoutProgramService->detachWorkout($program, $workout);

        return $program;
    }

    /**
     * Resolves a `program_id` field that is either a real database ID or a
     * `tmp_<client_uuid>` reference to a program created earlier — nesta
     * mesma chamada (mapa `$createdProgramsByUuid`) OU em um lote ANTERIOR já
     * sincronizado e removido da fila local. É esta segunda camada — buscar
     * no banco por `client_uuid` — que faltava: sem ela, criar um programa
     * offline e, antes do próximo full sync substituir o ID temporário,
     * mexer nele de novo (editar, arquivar, anexar uma ficha) em um lote
     * SEPARADO, rejeitava essa segunda ação com "Nenhum programa
     * correspondente encontrado" para sempre — mesmo o programa já existindo
     * de verdade no servidor.
     *
     * @param  array<string, WorkoutProgram>  $createdProgramsByUuid
     */
    private function resolveProgram(User $user, int|string $rawId, array $createdProgramsByUuid): WorkoutProgram
    {
        if (is_string($rawId) && str_starts_with($rawId, 'tmp_')) {
            $clientUuid = substr($rawId, 4);

            $program = $createdProgramsByUuid[$clientUuid]
                ?? WorkoutProgram::query()->where('user_id', $user->id)->where('client_uuid', $clientUuid)->first();

            if ($program === null) {
                throw ValidationException::withMessages([
                    'actions' => 'Nenhum programa correspondente foi encontrado para sincronizar esta ação.',
                ]);
            }

            return $program;
        }

        $program = WorkoutProgram::query()->where('id', $rawId)->where('user_id', $user->id)->first();

        if ($program === null) {
            throw ValidationException::withMessages([
                'actions' => 'Nenhum programa correspondente foi encontrado para sincronizar esta ação.',
            ]);
        }

        return $program;
    }

    /**
     * Resolves a `workout_id` field da mesma forma que `resolveProgram`
     * resolve `program_id` — incluindo o fallback por `client_uuid` no banco
     * para uma ficha criada em um lote anterior (ver comentário acima).
     *
     * @param  array<string, Workout>  $createdWorkoutsByUuid
     */
    private function resolveWorkout(User $user, int|string $rawId, array $createdWorkoutsByUuid): Workout
    {
        if (is_string($rawId) && str_starts_with($rawId, 'tmp_')) {
            $clientUuid = substr($rawId, 4);

            $workout = $createdWorkoutsByUuid[$clientUuid]
                ?? Workout::query()->where('user_id', $user->id)->where('client_uuid', $clientUuid)->first();

            if ($workout === null) {
                throw ValidationException::withMessages([
                    'actions' => 'Nenhum treino correspondente foi encontrado para sincronizar esta ação.',
                ]);
            }

            return $workout;
        }

        $workout = Workout::query()->where('id', $rawId)->where('user_id', $user->id)->first();

        if ($workout === null) {
            throw ValidationException::withMessages([
                'actions' => 'Nenhum treino correspondente foi encontrado para sincronizar esta ação.',
            ]);
        }

        return $workout;
    }
}
