// Camada de persistência local (IndexedDB) para o modo offline-first.
//
// Guarda quatro "gavetas" de dados no dispositivo:
//   - cached_workouts:   fichas de treino (lista + detalhe com exercícios)
//   - cached_exercises:  catálogo mestre de exercícios
//   - cached_last_logs:  último desempenho registrado por exercício
//   - sync_queue:        mutações feitas offline, pendentes de sincronização
//
// Toda a API é baseada em Promises para poder ser usada com async/await nos
// componentes Vue sem expor os callbacks nativos do IndexedDB.

const DB_NAME = 'ia-lift-offline';
const DB_VERSION = 1;

const STORE_WORKOUTS = 'cached_workouts';
const STORE_EXERCISES = 'cached_exercises';
const STORE_LAST_LOGS = 'cached_last_logs';
const STORE_SYNC_QUEUE = 'sync_queue';

// Chaves fixas usadas para guardar "instantâneos" de listas inteiras dentro
// de stores cujo keyPath é `id` (o mesmo store também guarda registros
// individuais, então essas chaves precisam ser valores que nunca colidem
// com um ID numérico de banco de dados).
const WORKOUTS_LIST_KEY = '__workouts_list__';
const EXERCISES_CATALOG_KEY = '__exercises_catalog__';

let dbPromise = null;

function openDatabase() {
    if (typeof indexedDB === 'undefined') {
        return Promise.reject(new Error('IndexedDB não está disponível neste ambiente.'));
    }

    if (!dbPromise) {
        dbPromise = new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);

            request.onupgradeneeded = () => {
                const db = request.result;

                if (!db.objectStoreNames.contains(STORE_WORKOUTS)) {
                    db.createObjectStore(STORE_WORKOUTS, { keyPath: 'id' });
                }
                if (!db.objectStoreNames.contains(STORE_EXERCISES)) {
                    db.createObjectStore(STORE_EXERCISES, { keyPath: 'id' });
                }
                if (!db.objectStoreNames.contains(STORE_LAST_LOGS)) {
                    db.createObjectStore(STORE_LAST_LOGS, { keyPath: 'exercise_id' });
                }
                if (!db.objectStoreNames.contains(STORE_SYNC_QUEUE)) {
                    db.createObjectStore(STORE_SYNC_QUEUE, { keyPath: 'uuid' });
                }
            };

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    return dbPromise;
}

async function withStore(storeName, mode, callback) {
    const db = await openDatabase();

    return new Promise((resolve, reject) => {
        const transaction = db.transaction(storeName, mode);
        const store = transaction.objectStore(storeName);

        let result;
        Promise.resolve(callback(store))
            .then((value) => { result = value; })
            .catch(reject);

        transaction.oncomplete = () => resolve(result);
        transaction.onerror = () => reject(transaction.error);
        transaction.onabort = () => reject(transaction.error);
    });
}

function requestToPromise(request) {
    return new Promise((resolve, reject) => {
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function generateUuid() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    // Fallback simples (RFC4122-like) para navegadores sem crypto.randomUUID.
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}

// ─── Fichas de treino (cached_workouts) ────────────────────────────────────

/**
 * Salva o instantâneo da lista de treinos (tela `Workouts/Index`) para
 * navegação offline.
 */
export async function cacheWorkoutsList(workouts) {
    return withStore(STORE_WORKOUTS, 'readwrite', (store) => {
        store.put({ id: WORKOUTS_LIST_KEY, items: workouts, cachedAt: new Date().toISOString() });
    });
}

export async function getCachedWorkoutsList() {
    const record = await withStore(STORE_WORKOUTS, 'readonly', (store) => requestToPromise(store.get(WORKOUTS_LIST_KEY)));
    return record?.items ?? [];
}

/**
 * Salva o detalhe completo de um treino (com exercícios), usado tanto pela
 * tela `Workouts/Show` quanto pela sessão ativa, para permitir abrir o
 * treino e iniciar/continuar uma sessão sem conexão.
 */
export async function cacheWorkout(workout) {
    if (!workout?.id) return;

    return withStore(STORE_WORKOUTS, 'readwrite', (store) => {
        store.put({ ...workout, cachedAt: new Date().toISOString() });
    });
}

export async function getCachedWorkout(id) {
    const numericId = Number(id);
    return withStore(STORE_WORKOUTS, 'readonly', (store) => requestToPromise(store.get(numericId)));
}

/**
 * Busca o detalhe de um treino para abertura offline, com fallback: se o
 * treino nunca foi aberto individualmente (nenhum `cacheWorkout()` prévio),
 * procura o mesmo id dentro do último instantâneo da lista (`Workouts/Index`),
 * que também carrega os exercícios de cada ficha.
 */
export async function getCachedWorkoutById(id) {
    const direct = await getCachedWorkout(id);
    if (direct) return direct;

    const numericId = Number(id);
    const list = await getCachedWorkoutsList();
    return list.find((workout) => workout.id === numericId) ?? null;
}

// ─── Catálogo de exercícios (cached_exercises) ─────────────────────────────

/**
 * Salva o catálogo mestre de exercícios agrupado por músculo (o mesmo
 * formato retornado por `WorkoutService::getAvailableExercises()`).
 */
export async function cacheExercisesCatalog(catalog) {
    return withStore(STORE_EXERCISES, 'readwrite', (store) => {
        store.put({ id: EXERCISES_CATALOG_KEY, groups: catalog, cachedAt: new Date().toISOString() });
    });
}

export async function getCachedExercisesCatalog() {
    const record = await withStore(STORE_EXERCISES, 'readonly', (store) => requestToPromise(store.get(EXERCISES_CATALOG_KEY)));
    return record?.groups ?? [];
}

// ─── Histórico recente de cargas (cached_last_logs) ────────────────────────

/**
 * Salva o histórico "última vez que fiz este exercício" por exercício
 * (mesmo formato de `lastLogs` retornado para a tela `WorkoutSessions/Active`).
 */
export async function cacheLastLogs(lastLogsByExerciseId) {
    return withStore(STORE_LAST_LOGS, 'readwrite', (store) => {
        for (const [exerciseId, log] of Object.entries(lastLogsByExerciseId ?? {})) {
            store.put({ ...log, exercise_id: Number(exerciseId) });
        }
    });
}

export async function getCachedLastLogs() {
    const rows = await withStore(STORE_LAST_LOGS, 'readonly', (store) => requestToPromise(store.getAll()));

    return rows.reduce((acc, row) => {
        acc[row.exercise_id] = row;
        return acc;
    }, {});
}

// ─── Fila de sincronização (sync_queue) ────────────────────────────────────

/**
 * Enfileira uma mutação (início de sessão, série registrada ou finalização
 * de treino) feita offline. `occurred_at` é o instante real em que a ação
 * aconteceu no dispositivo — é isso que o backend usa para reconstruir a
 * ordem cronológica correta ao sincronizar.
 */
export async function enqueueSyncAction(action) {
    const record = {
        uuid: action.uuid ?? generateUuid(),
        occurred_at: action.occurred_at ?? new Date().toISOString(),
        queued_at: new Date().toISOString(),
        ...action,
    };

    await withStore(STORE_SYNC_QUEUE, 'readwrite', (store) => {
        store.put(record);
    });

    return record;
}

/**
 * Retorna todas as ações pendentes, ordenadas pelo instante em que
 * ocorreram (mesmo critério aplicado no backend ao processar o lote).
 */
export async function getSyncQueue() {
    const rows = await withStore(STORE_SYNC_QUEUE, 'readonly', (store) => requestToPromise(store.getAll()));
    return rows.sort((a, b) => new Date(a.occurred_at) - new Date(b.occurred_at));
}

export async function countPendingSyncActions() {
    return withStore(STORE_SYNC_QUEUE, 'readonly', (store) => requestToPromise(store.count()));
}

export async function removeSyncActions(uuids) {
    return withStore(STORE_SYNC_QUEUE, 'readwrite', (store) => {
        for (const uuid of uuids) {
            store.delete(uuid);
        }
    });
}

export async function clearSyncQueue() {
    return withStore(STORE_SYNC_QUEUE, 'readwrite', (store) => {
        store.clear();
    });
}

// ─── Helpers ────────────────────────────────────────────────────────────────

export function isOffline() {
    return typeof navigator !== 'undefined' && navigator.onLine === false;
}

export { generateUuid };
