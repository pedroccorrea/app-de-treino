// Camada de persistência local (IndexedDB) para o modo offline-first.
//
// Guarda seis "gavetas" de dados no dispositivo:
//   - cached_workouts:   fichas de treino (lista + detalhe com exercícios)
//   - cached_programs:   programas de treino (lista + detalhe com fichas)
//   - cached_exercises:  catálogo mestre de exercícios
//   - cached_last_logs:  último desempenho registrado por exercício
//   - sync_queue:        mutações feitas offline, pendentes de sincronização
//   - meta:              pares chave/valor avulsos (versão do Inertia, data
//                         do último full sync) usados pelo mini-servidor do
//                         Service Worker (`resources/pwa/sw.js`)
//
// Toda a API é baseada em Promises para poder ser usada com async/await nos
// componentes Vue sem expor os callbacks nativos do IndexedDB.
//
// IMPORTANTE: o Service Worker (`resources/pwa/sw.js`) é registrado como
// script clássico (não como módulo ES), então ele não pode importar este
// arquivo — ele mantém sua própria cópia mínima, em JS puro, da mesma lógica
// de acesso ao IndexedDB. `DB_NAME`, `DB_VERSION` e os nomes das stores
// abaixo precisam continuar idênticos aos definidos lá.

import { isProxy, isRef, toRaw } from 'vue';

const DB_NAME = 'ia-lift-offline';
const DB_VERSION = 2;

const STORE_WORKOUTS = 'cached_workouts';
const STORE_PROGRAMS = 'cached_programs';
const STORE_EXERCISES = 'cached_exercises';
const STORE_LAST_LOGS = 'cached_last_logs';
const STORE_SYNC_QUEUE = 'sync_queue';
const STORE_META = 'meta';

// Chaves fixas usadas para guardar "instantâneos" de listas inteiras dentro
// de stores cujo keyPath é `id` (o mesmo store também guarda registros
// individuais, então essas chaves precisam ser valores que nunca colidem
// com um ID numérico de banco de dados).
const WORKOUTS_LIST_KEY = '__workouts_list__';
const PROGRAMS_LIST_KEY = '__programs_list__';
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
                if (!db.objectStoreNames.contains(STORE_PROGRAMS)) {
                    db.createObjectStore(STORE_PROGRAMS, { keyPath: 'id' });
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
                if (!db.objectStoreNames.contains(STORE_META)) {
                    db.createObjectStore(STORE_META, { keyPath: 'key' });
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

// O Vue entrega dados reativos (Proxies) para dentro dos componentes. O
// algoritmo de clonagem estruturada usado pelo IndexedDB não sabe lidar com
// Proxies (nem com Refs, Maps/Sets reativos ou referências circulares que a
// reatividade do Vue pode introduzir) e lança `DataCloneError` ao tentar
// gravá-los. `toRaw()` sozinho só remove UM nível de Proxy — propriedades
// aninhadas de um objeto reativo continuam sendo Proxies quando acessadas.
// Por isso `deepToRaw` percorre a estrutura inteira recursivamente, chamando
// `toRaw()`/desembrulhando Refs em cada nível, até sobrar apenas dados
// nativos (objetos, arrays, primitivos) seguros para `store.put()`/`add()`.
function deepToRaw(value, seen = new WeakMap()) {
    if (isRef(value)) {
        return deepToRaw(value.value, seen);
    }

    if (typeof value === 'function' || typeof value === 'symbol') {
        return undefined;
    }

    if (value === null || typeof value !== 'object') {
        return value;
    }

    if (value instanceof Date) {
        return value.toISOString();
    }

    const raw = isProxy(value) ? toRaw(value) : value;

    if (seen.has(raw)) {
        return seen.get(raw);
    }

    if (Array.isArray(raw)) {
        const cloned = [];
        seen.set(raw, cloned);
        raw.forEach((item, index) => { cloned[index] = deepToRaw(item, seen); });
        return cloned;
    }

    if (raw instanceof Map) {
        const cloned = {};
        seen.set(raw, cloned);
        raw.forEach((entryValue, key) => { cloned[key] = deepToRaw(entryValue, seen); });
        return cloned;
    }

    if (raw instanceof Set) {
        const cloned = [];
        seen.set(raw, cloned);
        raw.forEach((entryValue) => cloned.push(deepToRaw(entryValue, seen)));
        return cloned;
    }

    const cloned = {};
    seen.set(raw, cloned);
    for (const key of Object.keys(raw)) {
        const propValue = deepToRaw(raw[key], seen);
        if (propValue !== undefined) {
            cloned[key] = propValue;
        }
    }
    return cloned;
}

function toPlainObject(data) {
    return deepToRaw(data);
}

// IDs de registros criados offline (ainda não sincronizados) usam o prefixo
// `tmp_` e permanecem strings; IDs reais (vindos do banco) são sempre
// numéricos. Normaliza um id recebido (de uma rota, de um `:key` de lista
// etc.) para o tipo correto antes de consultar o IndexedDB, cujas chaves são
// tipadas (`1` e `"1"` são chaves diferentes).
function normalizeId(id) {
    if (typeof id === 'string' && id.startsWith('tmp_')) {
        return id;
    }

    return Number(id);
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
    const record = toPlainObject({ id: WORKOUTS_LIST_KEY, items: workouts, cachedAt: new Date().toISOString() });

    return withStore(STORE_WORKOUTS, 'readwrite', (store) => {
        store.put(record);
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

    const record = toPlainObject({ ...workout, cachedAt: new Date().toISOString() });

    return withStore(STORE_WORKOUTS, 'readwrite', (store) => {
        store.put(record);
    });
}

export async function getCachedWorkout(id) {
    return withStore(STORE_WORKOUTS, 'readonly', (store) => requestToPromise(store.get(normalizeId(id))));
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

    const targetId = normalizeId(id);
    const list = await getCachedWorkoutsList();
    return list.find((workout) => workout.id === targetId) ?? null;
}

/**
 * Remove um treino do "instantâneo" da lista e do registro individual — usado
 * ao aplicar, otimisticamente, uma exclusão feita offline.
 */
export async function removeCachedWorkout(id) {
    const targetId = normalizeId(id);

    const list = await getCachedWorkoutsList();
    await cacheWorkoutsList(list.filter((workout) => workout.id !== targetId));

    return withStore(STORE_WORKOUTS, 'readwrite', (store) => {
        store.delete(targetId);
    });
}

// ─── Programas de treino (cached_programs) ─────────────────────────────────

/**
 * Salva o instantâneo da lista de programas (tela `Programs/Index`) para
 * navegação offline.
 */
export async function cachePrograms(programs) {
    const record = toPlainObject({ id: PROGRAMS_LIST_KEY, items: programs, cachedAt: new Date().toISOString() });

    return withStore(STORE_PROGRAMS, 'readwrite', (store) => {
        store.put(record);
    });
}

export async function getCachedProgramsList() {
    const record = await withStore(STORE_PROGRAMS, 'readonly', (store) => requestToPromise(store.get(PROGRAMS_LIST_KEY)));
    return record?.items ?? [];
}

/**
 * Salva o detalhe completo de um programa (com suas fichas), usado pela tela
 * `Programs/Show` para permitir abrir o programa sem conexão.
 */
export async function cacheProgram(program) {
    if (!program?.id) return;

    const record = toPlainObject({ ...program, cachedAt: new Date().toISOString() });

    return withStore(STORE_PROGRAMS, 'readwrite', (store) => {
        store.put(record);
    });
}

export async function getCachedProgram(id) {
    return withStore(STORE_PROGRAMS, 'readonly', (store) => requestToPromise(store.get(normalizeId(id))));
}

/**
 * Busca o detalhe de um programa para abertura offline, com o mesmo
 * fallback usado em `getCachedWorkoutById`: se o programa nunca foi aberto
 * individualmente, procura o mesmo id dentro do último instantâneo da lista.
 */
export async function getCachedProgramById(id) {
    const direct = await getCachedProgram(id);
    if (direct) return direct;

    const targetId = normalizeId(id);
    const list = await getCachedProgramsList();
    return list.find((program) => program.id === targetId) ?? null;
}

export async function removeCachedProgram(id) {
    const targetId = normalizeId(id);

    const list = await getCachedProgramsList();
    await cachePrograms(list.filter((program) => program.id !== targetId));

    return withStore(STORE_PROGRAMS, 'readwrite', (store) => {
        store.delete(targetId);
    });
}

// ─── Catálogo de exercícios (cached_exercises) ─────────────────────────────

/**
 * Salva o catálogo mestre de exercícios agrupado por músculo (o mesmo
 * formato retornado por `WorkoutService::getAvailableExercises()`).
 */
export async function cacheExercisesCatalog(catalog) {
    const record = toPlainObject({ id: EXERCISES_CATALOG_KEY, groups: catalog, cachedAt: new Date().toISOString() });

    return withStore(STORE_EXERCISES, 'readwrite', (store) => {
        store.put(record);
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
    const plainLogs = toPlainObject(lastLogsByExerciseId ?? {});

    return withStore(STORE_LAST_LOGS, 'readwrite', (store) => {
        for (const [exerciseId, log] of Object.entries(plainLogs)) {
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
    const record = toPlainObject({
        uuid: action.uuid ?? generateUuid(),
        occurred_at: action.occurred_at ?? new Date().toISOString(),
        queued_at: new Date().toISOString(),
        ...action,
    });

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

// ─── Metadados (meta) ───────────────────────────────────────────────────────

async function setMeta(key, value) {
    return withStore(STORE_META, 'readwrite', (store) => {
        store.put(toPlainObject({ key, value }));
    });
}

async function getMeta(key) {
    const record = await withStore(STORE_META, 'readonly', (store) => requestToPromise(store.get(key)));
    return record?.value ?? null;
}

// ─── Full Sync (download inicial do estado global) ─────────────────────────

/**
 * Sobrescreve todo o estado offline com a resposta de `GET /api/sync/state`
 * (ver `SyncStateController` e `resources/js/Utils/syncState.js`): a lista
 * de programas e treinos, cada programa/treino individualmente (para abrir
 * o detalhe offline sem nunca tê-lo visitado antes), o catálogo de
 * exercícios, o histórico de últimas cargas e a versão atual do Inertia (que
 * o mini-servidor do Service Worker usa para montar páginas válidas).
 */
export async function saveFullSyncState(state) {
    const programs = state.programs ?? [];
    const workouts = state.workouts ?? [];

    await Promise.all([
        cachePrograms(programs),
        ...programs.map((program) => cacheProgram(program)),
        cacheWorkoutsList(workouts),
        ...workouts.map((workout) => cacheWorkout(workout)),
        cacheExercisesCatalog(state.exercisesCatalog ?? []),
        cacheLastLogs(state.lastLogs ?? {}),
        setMeta('inertiaVersion', state.version ?? null),
        setMeta('lastFullSyncAt', state.syncedAt ?? new Date().toISOString()),
    ]);
}

export async function getStoredInertiaVersion() {
    return getMeta('inertiaVersion');
}

export async function getLastFullSyncAt() {
    return getMeta('lastFullSyncAt');
}

// ─── Helpers ────────────────────────────────────────────────────────────────

export function isOffline() {
    return typeof navigator !== 'undefined' && navigator.onLine === false;
}

export { generateUuid, normalizeId };
