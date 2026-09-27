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

    const incoming = toPlainObject({ ...workout, cachedAt: new Date().toISOString() });

    // MESCLA em vez de sobrescrever. Telas diferentes conhecem recortes
    // diferentes do mesmo treino: `Workouts/Show` traz `muscle_groups`,
    // `days_of_week`, `is_active`…; `WorkoutSessions/Active` traz apenas
    // `{id, name, description}` mais os exercícios com `rest_seconds`. Um
    // `put()` cru fazia a segunda tela apagar os campos que só a primeira
    // conhece — e era exatamente isso que quebrava a renderização offline de
    // `Workouts/Show` (`workout.muscle_groups.length` sobre `undefined`, que
    // aborta o patch do Vue e deixa só o cabeçalho na tela) e sumia com o
    // treino da Dashboard offline (`is_active` ausente).
    return withStore(STORE_WORKOUTS, 'readwrite', async (store) => {
        const existing = await requestToPromise(store.get(incoming.id));
        store.put(existing ? { ...existing, ...incoming } : incoming);
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

/**
 * Carimba `last_completed_at` no registro individual do treino e na sua
 * entrada dentro do instantâneo da lista — chamado imediatamente ao
 * finalizar uma sessão (online ou offline), para que a cópia local do
 * treino reflita a conclusão sem esperar o próximo full sync (Phase 36).
 */
export async function markWorkoutCompletedLocally(id, completedAt = new Date().toISOString()) {
    if (!id) return;

    const targetId = normalizeId(id);

    const [individual, list] = await Promise.all([getCachedWorkout(targetId), getCachedWorkoutsList()]);

    if (individual) {
        await cacheWorkout({ ...individual, last_completed_at: completedAt });
    }

    const listIndex = list.findIndex((workout) => workout.id === targetId);
    if (listIndex !== -1) {
        const updatedList = [...list];
        updatedList[listIndex] = { ...updatedList[listIndex], last_completed_at: completedAt };
        await cacheWorkoutsList(updatedList);
    }
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

// ─── Sessão ativa local (meta: active_session) ─────────────────────────────
//
// Cópia local, gravada e apagada de forma imediata (sem esperar confirmação
// do servidor nem a sincronização da fila), de qual sessão de treino está em
// andamento neste dispositivo. É essa cópia — não a prop `activeWorkoutSession`
// compartilhada pelo Inertia, que pode vir de uma resposta HTTP já cacheada
// e desatualizada — que `ActiveSessionBanner.vue` e `Dashboard.vue` consultam
// para decidir se exibem o indicador de "treino em andamento" em modo offline.

/**
 * Registra a sessão atualmente em andamento. Chamado tanto ao iniciar
 * quanto ao reabrir/continuar uma sessão (online ou offline).
 */
export async function setActiveSession(session) {
    if (!session?.id) return;

    // Mesma sessão já registrada: mescla, nunca sobrescreve. O registro
    // acumula informação vinda de fontes diferentes (a prop compartilhada do
    // Inertia, o mount de `WorkoutSessions/Active.vue`, cada série concluída),
    // e perder `set_logs` aqui significaria voltar ao treino sem o progresso.
    const existing = await getActiveSession();
    const incoming = toPlainObject(session);

    const merged = existing && String(existing.id) === String(incoming.id)
        ? { ...existing, ...incoming }
        : incoming;

    return setMeta('active_session', merged);
}

/**
 * Registra (ou atualiza) uma série concluída dentro da sessão ativa local.
 * É esse histórico — gravado tanto no caminho online quanto no offline — que
 * permite sair da tela do treino, navegar pelo app e voltar depois com todas
 * as séries já feitas marcadas, mesmo sem nenhuma conexão (Phase 38).
 */
export async function recordActiveSessionSetLog(log) {
    const session = await getActiveSession();
    if (!session) return;

    const entry = toPlainObject({
        exercise_id: log.exercise_id,
        set_number: log.set_number,
        weight: log.weight === '' || log.weight === undefined ? null : log.weight,
        reps: log.reps === '' || log.reps === undefined ? null : log.reps,
    });

    const logs = Array.isArray(session.set_logs) ? [...session.set_logs] : [];
    const index = logs.findIndex(
        (item) => item.exercise_id === entry.exercise_id && item.set_number === entry.set_number,
    );

    if (index === -1) {
        logs.push(entry);
    } else {
        logs[index] = entry;
    }

    return setMeta('active_session', { ...session, set_logs: logs });
}

/**
 * Troca o ID temporário de uma sessão nascida offline pelo ID real criado no
 * servidor durante a sincronização, preservando o progresso já gravado. Sem
 * isso o indicador global continuaria apontando para um ID que só existe
 * neste dispositivo mesmo depois de a sessão virar real (Phase 38).
 */
export async function promoteActiveSessionId(clientSessionId, realSessionId) {
    const session = await getActiveSession();

    if (!session || String(session.id) !== String(clientSessionId)) return false;

    await setMeta('active_session', {
        ...session,
        id: realSessionId,
        client_session_id: `session-${realSessionId}`,
        offline_only: false,
    });

    return true;
}

export async function getActiveSession() {
    return getMeta('active_session');
}

/**
 * Remove a sessão ativa local — chamado imediatamente ao finalizar um
 * treino, antes mesmo de qualquer confirmação do servidor, para que o
 * dispositivo nunca continue "achando" que há uma sessão em andamento.
 */
export async function clearActiveSession() {
    return withStore(STORE_META, 'readwrite', (store) => {
        store.delete('active_session');
    });
}

// ─── Dashboard (estado derivado para uso offline) ──────────────────────────

function todayIsoDayOfWeek() {
    const jsDay = new Date().getDay();
    return jsDay === 0 ? 7 : jsDay;
}

/**
 * Recalcula o estado da Dashboard (Card Hero + lista de treinos ativos)
 * inteiramente a partir do IndexedDB local. Usado por `Dashboard.vue` quando
 * o dispositivo está offline, para que o que aparece na tela nunca dependa
 * de uma prop vinda de uma resposta HTTP já cacheada — espelha a mesma
 * lógica que `resources/pwa/sw.js` usa para montar esta página no
 * mini-servidor (`buildDashboardProps`), mas roda direto no componente Vue,
 * cobrindo também navegações client-side do Inertia que não chegam a passar
 * pelo Service Worker.
 */
export async function getOfflineDashboardState() {
    const [workouts, programs] = await Promise.all([getCachedWorkoutsList(), getCachedProgramsList()]);
    const today = todayIsoDayOfWeek();

    const activeProgram = programs.find((program) => program.is_active) ?? null;
    const activeWorkouts = workouts.filter((workout) => workout.is_active);

    const todayWorkout = activeProgram
        ? activeWorkouts.find((workout) => workout.program_ids?.includes(activeProgram.id) && workout.days_of_week?.includes(today))
        : null;

    return {
        todayWorkout: todayWorkout ? {
            id: todayWorkout.id,
            name: todayWorkout.name,
            muscle_groups: todayWorkout.muscle_groups ?? [],
            exercises_count: todayWorkout.exercises_count ?? 0,
            program_name: activeProgram?.name ?? null,
        } : null,
        activeWorkouts: activeWorkouts.map((workout) => ({
            id: workout.id,
            name: workout.name,
            exercises_count: workout.exercises_count ?? 0,
            days_of_week_labels: workout.days_of_week_labels ?? [],
        })),
    };
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

/**
 * Reconstrói a lista de séries já registradas de uma sessão a partir da
 * própria fila de sincronização — o mesmo formato do prop `setLogs` que o
 * `WorkoutSessionController@show` envia online. Usado ao reabrir um treino
 * em andamento offline: a fila é o registro físico do que o usuário fez
 * neste aparelho, então ela é a fonte de verdade enquanto não sincroniza.
 */
export async function getQueuedSetLogs(clientSessionId) {
    if (!clientSessionId) return [];

    const rows = await getSyncQueue();
    const byPosition = new Map();

    for (const row of rows) {
        if (row.type !== 'log_set') continue;
        if (String(row.client_session_id) !== String(clientSessionId)) continue;

        // A fila é ordenada por `occurred_at`, então a última ocorrência de
        // um mesmo (exercício, série) é a que vale — igual ao
        // `updateOrCreate` que o backend aplica ao sincronizar.
        byPosition.set(`${row.exercise_id}-${row.set_number}`, {
            exercise_id: row.exercise_id,
            set_number: row.set_number,
            weight: row.weight ?? null,
            reps: row.reps ?? null,
            rpe: row.rpe ?? null,
            is_warmup: Boolean(row.is_warmup),
            notes: row.notes ?? null,
        });
    }

    return [...byPosition.values()];
}

/**
 * Séries já feitas na sessão em andamento, unindo as duas memórias locais: o
 * que a fila de sincronização ainda guarda (feito offline, não enviado) e o
 * que ficou carimbado no próprio registro da sessão ativa (feito online, ou
 * offline e já sincronizado — a fila é esvaziada nesse caso).
 */
export async function getActiveSessionSetLogs() {
    const session = await getActiveSession();
    if (!session) return [];

    const stamped = Array.isArray(session.set_logs) ? session.set_logs : [];
    const queued = await getQueuedSetLogs(session.client_session_id ?? session.id);

    const byPosition = new Map();
    for (const log of [...stamped, ...queued]) {
        byPosition.set(`${log.exercise_id}-${log.set_number}`, {
            exercise_id: log.exercise_id,
            set_number: log.set_number,
            weight: log.weight ?? null,
            reps: log.reps ?? null,
            rpe: log.rpe ?? null,
            is_warmup: Boolean(log.is_warmup),
            notes: log.notes ?? null,
        });
    }

    return [...byPosition.values()];
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

/**
 * Registra mais uma rejeição do servidor para cada ação em `uuids` (ação
 * fora de `results` numa resposta 200, ou apontada pelo 422 estrutural).
 * As que atingem `maxAttempts` são consideradas falha definitiva: saem da
 * fila e são devolvidas para quem chamou poder registrá-las no console.
 * Sem esse teto, uma ação permanentemente irresolúvel (ex.: `start_session`
 * de uma ficha já excluída) seria reenviada para sempre.
 */
export async function registerSyncRejections(uuids, maxAttempts) {
    if (uuids.length === 0) return [];

    const wanted = new Set(uuids);
    const rows = (await getSyncQueue()).filter((row) => wanted.has(row.uuid));
    const discarded = [];

    await withStore(STORE_SYNC_QUEUE, 'readwrite', (store) => {
        for (const row of rows) {
            const attempts = (row.attempts ?? 0) + 1;

            if (attempts >= maxAttempts) {
                store.delete(row.uuid);
                discarded.push({ ...row, attempts });
            } else {
                store.put({ ...row, attempts });
            }
        }
    });

    return discarded;
}

/**
 * Há um `finish_session` ainda não sincronizado para esta sessão real? Nesse
 * caso o aparelho já sabe que o treino acabou, mesmo que o servidor (que
 * ainda não recebeu a finalização) continue dizendo que ele está aberto.
 */
export async function hasPendingFinishForSession(sessionId) {
    if (sessionId === null || sessionId === undefined) return false;

    const id = String(sessionId);
    const rows = await getSyncQueue();

    return rows.some((row) => row.type === 'finish_session'
        && (String(row.session_id) === id || row.client_session_id === id || row.client_session_id === `session-${id}`));
}

export async function clearSyncQueue() {
    return withStore(STORE_SYNC_QUEUE, 'readwrite', (store) => {
        store.clear();
    });
}

// ─── Snapshot do usuário autenticado (meta: auth_user) ─────────────────────
//
// Cópia local mínima (`id`, `name`, `email`) do usuário logado, gravada
// sempre que uma sincronização acontece com sucesso enquanto online.
// `Sidebar.vue` recorre a ela quando o Service Worker serve uma página
// sintética (ver `resources/pwa/sw.js`) sem a prop `auth.user` completa, para
// que o nome do usuário nunca quebre a renderização nem apareça vazio.

export async function setCachedAuthUser(user) {
    if (!user?.id) return;

    return setMeta('auth_user', toPlainObject({ id: user.id, name: user.name, email: user.email }));
}

export async function getCachedAuthUser() {
    return getMeta('auth_user');
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
