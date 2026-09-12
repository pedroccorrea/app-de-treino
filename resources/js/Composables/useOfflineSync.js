import { ref } from 'vue';
import * as offlineStorage from '@/Utils/offlineStorage';
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
 * Envia a fila de mutações offline — início de sessão, séries, finalização
 * de treino e CRUD de programas/treinos — para os endpoints de sincronização
 * em lote assim que a conexão volta. Ações confirmadas pelo servidor são
 * removidas da fila; qualquer falha de rede simplesmente deixa a fila (ou o
 * que sobrar dela) intacta para a próxima tentativa. Ao final, busca a foto
 * completa do estado (`fetchAndStoreFullSyncState`) para que os IDs
 * temporários usados enquanto offline sejam substituídos pelos IDs reais em
 * toda a UI, em silêncio.
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
        const syncedUuids = [];

        if (sessionActions.length > 0) {
            const response = await window.axios.post(route('sync.workout-sessions'), { actions: sessionActions });
            syncedUuids.push(...(response.data?.results ?? []).map((result) => result.uuid));
        }

        if (resourceActions.length > 0) {
            const response = await window.axios.post(route('sync.mutations'), { actions: resourceActions });
            const results = response.data?.results ?? [];

            for (const result of results) {
                await forgetTemporaryRecord(result);
            }

            syncedUuids.push(...results.map((result) => result.uuid));
        }

        if (syncedUuids.length > 0) {
            await offlineStorage.removeSyncActions(syncedUuids);
        }

        await fetchAndStoreFullSyncState();
    } catch (_) {
        // Sem conexão de verdade (ou erro do servidor): a fila permanece
        // intacta e será retentada no próximo evento `online` ou flush manual.
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

export function useOfflineSync() {
    registerGlobalListeners();

    return { isOnline, syncing, pendingCount, queueAction, flushQueue };
}
