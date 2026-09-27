import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import * as offlineStorage from '@/Utils/offlineStorage';
import { evictInertiaCache } from '@/Utils/serviceWorkerMessaging';
import { fetchAndStoreFullSyncState } from '@/Utils/syncState';

// Estado compartilhado entre todos os componentes que usam este composable
// (é sempre a mesma conexão de internet e a mesma fila, então não faz
// sentido cada componente ter sua própria cópia).
const isOnline = ref(typeof navigator === 'undefined' || navigator.onLine);
const syncing = ref(false);
const pendingCount = ref(0);
let listenersRegistered = false;

// Tipos de ação que pertencem a uma sessão de treino (endpoint
// `sync.workout-sessions`); qualquer outro tipo na fila é uma mutação de
// CRUD (programa/treino) e vai para `sync.mutations` — ver
// `SyncService::processWorkoutSessionActions` vs. `processResourceMutations`.
const SESSION_ACTION_TYPES = ['start_session', 'log_set', 'finish_session'];

async function refreshPendingCount() {
    try {
        pendingCount.value = await offlineStorage.countPendingSyncActions();
    } catch (_) {
        // IndexedDB indisponível (ex.: modo privado); a contagem só não some da UI.
    }
}

/**
 * Um `create_program`/`create_workout` foi sincronizado: o registro
 * otimista salvo offline sob o ID temporário (`tmp_<client_uuid>`) não tem
 * mais utilidade — a atualização de `fetchAndStoreFullSyncState()`, chamada
 * logo em seguida, já grava a versão real (com o ID definitivo) vinda do
 * servidor. Isso só limpa a sobra.
 */
async function forgetTemporaryRecord(result) {
    if (!result.client_uuid) return;

    const tempId = `tmp_${result.client_uuid}`;

    if (result.type === 'create_program') {
        await offlineStorage.removeCachedProgram(tempId);
    } else if (result.type === 'create_workout') {
        await offlineStorage.removeCachedWorkout(tempId);
    }
}

// Quantas vezes o servidor pode RECUSAR uma mesma ação (resposta recebida,
// mas a ação ficou de fora de `results` ou foi apontada por um 422) antes de
// ela ser tratada como falha definitiva e removida da fila. Quedas de rede e
// erros 5xx não contam: nesses casos o servidor nem chegou a avaliar a ação.
// A margem acima de 1 existe para rejeições temporárias legítimas — ex.: um
// `start_session` apontando para `tmp_<uuid>` cuja ficha ainda não subiu.
const MAX_SYNC_ATTEMPTS = 5;

/**
 * Índices das ações apontadas por um 422 estrutural (`actions.3.reps` → 3).
 */
function rejectedIndexesFrom422(error) {
    const keys = Object.keys(error?.response?.data?.errors ?? {});
    const indexes = keys
        .map((key) => key.match(/^actions\.(\d+)\./)?.[1])
        .filter((index) => index !== undefined)
        .map(Number);

    return [...new Set(indexes)];
}

async function registerRejections(actions) {
    const discarded = await offlineStorage.registerSyncRejections(
        actions.map((action) => action.uuid),
        MAX_SYNC_ATTEMPTS,
    );

    for (const action of discarded) {
        console.warn(
            `[sync] Ação descartada da fila após ${action.attempts} rejeições do servidor (falha definitiva).`,
            action,
        );
    }
}

/**
 * Envia um grupo de ações (sessões OU mutações de recurso) para `url` e
 * remove da fila local SOMENTE as que o servidor confirmou explicitamente
 * (HTTP 200 com o `uuid` da ação presente em `results`). As que o servidor
 * avaliou e recusou ganham +1 tentativa (ver `MAX_SYNC_ATTEMPTS`). Queda de
 * rede ou erro 5xx deixa o grupo intacto, sem contar tentativa.
 *
 * Retorna `{ reachedServer, allSynced }`: `allSynced` decide, em
 * `flushQueue`, se já dá para buscar a foto completa do estado
 * (`fetchAndStoreFullSyncState`); `reachedServer` decide se vale recarregar
 * as props da página atual.
 */
async function syncActionGroup(url, actions, onResult) {
    if (actions.length === 0) return { reachedServer: false, allSynced: true };

    // `attempts` é contabilidade local da fila, não faz parte do contrato.
    const payload = actions.map(({ attempts, ...action }) => action);

    try {
        const response = await window.axios.post(url, { actions: payload });
        const results = response.data?.results ?? [];

        if (onResult) {
            for (const result of results) {
                await onResult(result);
            }
        }

        const synced = new Set(results.map((result) => result.uuid));
        await offlineStorage.removeSyncActions([...synced]);
        await registerRejections(actions.filter((action) => !synced.has(action.uuid)));

        return { reachedServer: true, allSynced: synced.size === actions.length };
    } catch (error) {
        const status = error?.response?.status;

        if (status === 422) {
            console.error('[sync] Lote rejeitado por validação estrutural (422); dados mantidos na fila local para nova tentativa.', error.response?.data);
            await registerRejections(rejectedIndexesFrom422(error).map((index) => actions[index]).filter(Boolean));

            return { reachedServer: true, allSynced: false };
        }

        console.warn('[sync] Falha ao sincronizar com o servidor; dados mantidos na fila local para nova tentativa.', error?.message ?? error);

        return { reachedServer: false, allSynced: false };
    }
}

/**
 * Uma ação de sessão foi confirmada pelo servidor: se a sessão em andamento
 * neste aparelho era a que nasceu offline (o `id` local é o próprio
 * `client_session_id`), troca o ID temporário pelo ID real recém-criado. Sem
 * isso, o indicador global de "treino em andamento" continuaria apontando
 * para um ID que só existe aqui, e voltar ao treino depois da reconexão
 * levaria a uma tela que o servidor não sabe montar.
 */
async function reconcileSyncedSession(result) {
    if (!result?.session_id || !result?.client_session_id) return;

    const promoted = await offlineStorage.promoteActiveSessionId(result.client_session_id, result.session_id);

    if (promoted) evictInertiaCache();
}

/**
 * Envia a fila de mutações offline — início de sessão, séries, finalização
 * de treino e CRUD de programas/treinos — para os endpoints de sincronização
 * em lote assim que a conexão volta. Cada grupo (sessões / mutações de
 * recurso) é enviado e confirmado independentemente — ver `syncActionGroup`.
 * Ao final, se AMBOS os grupos foram confirmados, busca a foto completa do
 * estado (`fetchAndStoreFullSyncState`) para que os IDs temporários usados
 * enquanto offline sejam substituídos pelos IDs reais em toda a UI, em
 * silêncio.
 *
 * Retorna `true` quando algum lote chegou de fato ao servidor — ou seja, o
 * estado do servidor pode ter mudado e as props da página atual ficaram
 * velhas.
 */
async function flushQueue() {
    if (typeof navigator !== 'undefined' && !navigator.onLine) return false;
    if (syncing.value) return false;

    const actions = await offlineStorage.getSyncQueue();
    if (actions.length === 0) {
        await refreshPendingCount();
        return false;
    }

    syncing.value = true;
    try {
        const sessionActions = actions.filter((action) => SESSION_ACTION_TYPES.includes(action.type));
        const resourceActions = actions.filter((action) => !SESSION_ACTION_TYPES.includes(action.type));

        // Recursos ANTES de sessões, sempre. Uma ficha criada offline e
        // treinada na mesma janela sem internet produz um `start_session`
        // que aponta para `tmp_<uuid>`: só depois que a ficha existe de
        // verdade no banco é que essa referência tem como ser resolvida.
        const resources = await syncActionGroup(route('sync.mutations'), resourceActions, forgetTemporaryRecord);
        const sessions = await syncActionGroup(route('sync.workout-sessions'), sessionActions, reconcileSyncedSession);

        if (sessions.allSynced && resources.allSynced) {
            await fetchAndStoreFullSyncState();
        }

        return sessions.reachedServer || resources.reachedServer;
    } finally {
        syncing.value = false;
        await refreshPendingCount();
    }
}

/**
 * Reconexão: primeiro esvazia a fila, SÓ DEPOIS recarrega as props da
 * página atual. Na ordem inversa, o servidor responderia antes de receber o
 * `finish_session` feito offline — e a prop `activeWorkoutSession` traria
 * de volta o banner "Treino em andamento" de um treino já finalizado.
 */
async function handleReconnect() {
    isOnline.value = true;

    const serverStateChanged = await flushQueue();

    if (serverStateChanged) {
        router.reload();
    }
}

function registerGlobalListeners() {
    if (listenersRegistered || typeof window === 'undefined') return;
    listenersRegistered = true;

    window.addEventListener('online', handleReconnect);
    window.addEventListener('offline', () => {
        isOnline.value = false;
    });

    refreshPendingCount();
    if (navigator.onLine) {
        flushQueue().then(() => {
            // Sem fila pendente, `flushQueue` não busca o estado completo
            // sozinho — isso garante que o download inicial (task 1) também
            // acontece num carregamento comum, sem nenhuma mutação pendente.
            fetchAndStoreFullSyncState();
        });
    }
}

/**
 * Enfileira uma ação offline e atualiza o contador de pendências exibido na UI.
 */
async function queueAction(action) {
    const record = await offlineStorage.enqueueSyncAction(action);
    await refreshPendingCount();
    return record;
}

/**
 * Registra localmente (IndexedDB) qual sessão está em andamento e avisa o
 * Service Worker para expurgar as páginas Inertia cacheadas — chamado ao
 * abrir/continuar uma sessão de treino, online ou offline (Phase 36).
 */
async function startActiveSessionLocally(session) {
    await offlineStorage.setActiveSession(session);
    evictInertiaCache();
}

/**
 * Encerra a sessão ativa localmente e avisa o Service Worker para expurgar
 * as páginas Inertia cacheadas — chamado imediatamente ao finalizar um
 * treino, sem esperar a confirmação do servidor nem a sincronização da fila,
 * para que o dispositivo nunca continue "achando" que há uma sessão em
 * andamento (Phase 36). Quando o id do treino é conhecido, também carimba a
 * conclusão na cópia local do treino (`last_completed_at`), para que o
 * status fique correto mesmo antes do próximo full sync.
 */
async function finishActiveSessionLocally(workoutId = null) {
    await offlineStorage.clearActiveSession();
    if (workoutId) {
        await offlineStorage.markWorkoutCompletedLocally(workoutId);
    }
    evictInertiaCache();
}

export function useOfflineSync() {
    registerGlobalListeners();

    return {
        isOnline,
        syncing,
        pendingCount,
        queueAction,
        flushQueue,
        startActiveSessionLocally,
        finishActiveSessionLocally,
        notifySessionChanged: evictInertiaCache,
    };
}
