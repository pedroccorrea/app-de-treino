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

/**
 * Envia um grupo de ações (sessões OU mutações de recurso) para `url` e
 * remove da fila local SOMENTE as que o servidor confirmou explicitamente
 * (HTTP 200 com o `uuid` da ação presente em `results`). Qualquer falha —
 * erro de validação (422), erro de servidor (500) ou queda de rede — deixa
 * esse grupo inteiro intacto na fila local: nada é perdido, e a tentativa
 * será repetida no próximo flush. Retorna `true` só quando o grupo inteiro
 * foi confirmado, usado por `flushQueue` para decidir se já pode buscar a
 * foto completa do estado (`fetchAndStoreFullSyncState`).
 */
async function syncActionGroup(url, actions, onResult) {
    if (actions.length === 0) return true;

    try {
        const response = await window.axios.post(url, { actions });
        const results = response.data?.results ?? [];

        if (onResult) {
            for (const result of results) {
                await onResult(result);
            }
        }

        await offlineStorage.removeSyncActions(results.map((result) => result.uuid));

        return results.length === actions.length;
    } catch (error) {
        const status = error?.response?.status;

        if (status === 422) {
            console.error('[sync] Lote rejeitado por validação (422); dados mantidos na fila local para nova tentativa.', error.response?.data);
        } else {
            console.error('[sync] Falha ao sincronizar com o servidor; dados mantidos na fila local para nova tentativa.', error);
        }

        return false;
    }
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
 */
async function flushQueue() {
    if (typeof navigator !== 'undefined' && !navigator.onLine) return;
    if (syncing.value) return;

    const actions = await offlineStorage.getSyncQueue();
    if (actions.length === 0) {
        await refreshPendingCount();
        return;
    }

    syncing.value = true;
    try {
        const sessionActions = actions.filter((action) => SESSION_ACTION_TYPES.includes(action.type));
        const resourceActions = actions.filter((action) => !SESSION_ACTION_TYPES.includes(action.type));

        const sessionsOk = await syncActionGroup(route('sync.workout-sessions'), sessionActions);
        const resourcesOk = await syncActionGroup(route('sync.mutations'), resourceActions, forgetTemporaryRecord);

        if (sessionsOk && resourcesOk) {
            await fetchAndStoreFullSyncState();
        }
    } finally {
        syncing.value = false;
        await refreshPendingCount();
    }
}

function registerGlobalListeners() {
    if (listenersRegistered || typeof window === 'undefined') return;
    listenersRegistered = true;

    window.addEventListener('online', () => {
        isOnline.value = true;
        flushQueue();
    });
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
