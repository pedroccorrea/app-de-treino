import { ref } from 'vue';
import * as offlineStorage from '@/Utils/offlineStorage';

// Estado compartilhado entre todos os componentes que usam este composable
// (é sempre a mesma conexão de internet e a mesma fila, então não faz
// sentido cada componente ter sua própria cópia).
const isOnline = ref(typeof navigator === 'undefined' || navigator.onLine);
const syncing = ref(false);
const pendingCount = ref(0);
let listenersRegistered = false;

async function refreshPendingCount() {
    try {
        pendingCount.value = await offlineStorage.countPendingSyncActions();
    } catch (_) {
        // IndexedDB indisponível (ex.: modo privado); a contagem só não some da UI.
    }
}

/**
 * Envia a fila de mutações offline (início de sessão, séries e finalização
 * de treino) para o endpoint de sincronização em lote assim que a conexão
 * volta. Ações confirmadas pelo servidor são removidas da fila; qualquer
 * falha de rede simplesmente deixa a fila intacta para a próxima tentativa.
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
        const response = await window.axios.post(route('sync.workout-sessions'), { actions });
        const syncedUuids = (response.data?.results ?? []).map((result) => result.uuid);

        if (syncedUuids.length > 0) {
            await offlineStorage.removeSyncActions(syncedUuids);
        }
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
        flushQueue();
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
