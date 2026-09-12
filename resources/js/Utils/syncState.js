// Download inicial do estado global (Phase 33, task 1): busca, em uma única
// requisição, todo o estado do usuário (`GET /api/sync/state`) e sobrescreve
// o IndexedDB com o resultado. É essa "foto" que permite ao mini-servidor do
// Service Worker (`resources/pwa/sw.js`) montar páginas Inertia válidas para
// rotas que este dispositivo nunca visitou enquanto online.

import { saveFullSyncState } from '@/Utils/offlineStorage';

/**
 * Busca `/api/sync/state` e grava o resultado no IndexedDB. Silencioso por
 * natureza: se a rede falhar (ex.: a conexão caiu entre o evento `online` e
 * esta chamada), os dados offline anteriores simplesmente permanecem como
 * estavam — não há nada de errado a mostrar ao usuário por isso.
 */
export async function fetchAndStoreFullSyncState() {
    try {
        const response = await window.axios.get(route('sync.state'));
        await saveFullSyncState(response.data);
        return true;
    } catch (_) {
        return false;
    }
}
