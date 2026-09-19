const CACHE_VERSION = 'v10';
const APP_SHELL_CACHE = `setwave-shell-${CACHE_VERSION}`;
const STATIC_ASSETS_CACHE = `setwave-assets-${CACHE_VERSION}`;
const INERTIA_CACHE = `setwave-inertia-${CACHE_VERSION}`;
const PAGE_CACHE = `setwave-pages-${CACHE_VERSION}`;

const CURRENT_CACHES = [APP_SHELL_CACHE, STATIC_ASSETS_CACHE, INERTIA_CACHE, PAGE_CACHE];

const APP_SHELL_ASSETS = [
    '/manifest.json',
    '/favicon.ico',
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png',
];

// Servido apenas quando o navegador pede uma navegação de documento completo
// (ex: F5) sem internet e nenhuma página ainda foi cacheada — evita a tela
// de erro nativa "sem conexão" do navegador na pior das hipóteses.
const OFFLINE_FALLBACK_HTML = `<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <title>Setwave — Offline</title>
    </head>
    <body style="font-family: sans-serif; text-align: center; padding: 2rem;">
        <h1>Você está offline</h1>
        <p>Conecte-se à internet para carregar esta página pela primeira vez.</p>
    </body>
</html>`;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(APP_SHELL_CACHE)
            // `cache.addAll()` é tudo-ou-nada: se UM único asset falhar (uma
            // falha de rede passageira, um ícone temporariamente
            // indisponível), a instalação INTEIRA falha — o Service Worker
            // nunca ativa, e a página nunca fica sob controle nenhum,
            // offline ou online. `Promise.allSettled` cacheia o que
            // conseguir e nunca deixa uma falha isolada derrubar a ativação
            // inteira do worker.
            .then((cache) => Promise.allSettled(
                APP_SHELL_ASSETS.map((asset) => cache.add(asset)),
            ))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((cacheNames) => Promise.all(
                cacheNames
                    .filter((cacheName) => !CURRENT_CACHES.includes(cacheName))
                    .map((cacheName) => caches.delete(cacheName))
            ))
            .then(() => self.clients.claim())
    );
});

// Rotas cujo JSON cacheado no `INERTIA_CACHE` pode ficar defasado assim que
// uma sessão de treino é iniciada, alterada ou finalizada (ex: o dashboard
// e a listagem de treinos deixam de refletir a sessão em andamento). O
// frontend avisa este Service Worker via `postMessage` nesses três momentos
// (ver `resources/js/Utils/serviceWorkerMessaging.js`) para que a próxima
// leitura offline dessas páginas nunca sirva a resposta antiga guardada
// aqui — cai direto no mini-servidor (dados reais do IndexedDB) definido
// mais abaixo.
const EVICTABLE_INERTIA_PATH_PATTERNS = [
    /^\/dashboard(\/.*)?$/,
    /^\/workouts(\/.*)?$/,
    /^\/programs(\/.*)?$/,
    /^\/workout-sessions(\/.*)?$/,
];

function isEvictableInertiaPath(pathname) {
    return EVICTABLE_INERTIA_PATH_PATTERNS.some((pattern) => pattern.test(pathname));
}

async function evictInertiaCache() {
    const cache = await caches.open(INERTIA_CACHE);
    const requests = await cache.keys();

    await Promise.all(
        requests
            .filter((request) => isEvictableInertiaPath(new URL(request.url).pathname))
            .map((request) => cache.delete(request))
    );
}

self.addEventListener('message', (event) => {
    if (event.data?.type !== 'EVICT_INERTIA_CACHE') return;

    event.waitUntil(evictInertiaCache());
});

// ─────────────────────────────────────────────────────────────────────────
// Mini-servidor (IndexedDB): o Service Worker é registrado como script
// clássico (`navigator.serviceWorker.register('/sw.js')`, sem `type:
// 'module'`), então ele não pode importar `resources/js/Utils/offlineStorage.js`.
// O bloco abaixo é a cópia mínima, em JS puro, da mesma camada de acesso ao
// IndexedDB — os nomes do banco/das stores precisam continuar idênticos aos
// definidos lá.
// ─────────────────────────────────────────────────────────────────────────

const DB_NAME = 'ia-lift-offline';
const DB_VERSION = 2;

const STORE_WORKOUTS = 'cached_workouts';
const STORE_PROGRAMS = 'cached_programs';
const STORE_EXERCISES = 'cached_exercises';
const STORE_LAST_LOGS = 'cached_last_logs';
const STORE_SYNC_QUEUE = 'sync_queue';
const STORE_META = 'meta';

const WORKOUTS_LIST_KEY = '__workouts_list__';
const PROGRAMS_LIST_KEY = '__programs_list__';
const EXERCISES_CATALOG_KEY = '__exercises_catalog__';

const DAY_SHORT_LABELS = { 1: 'Seg', 2: 'Ter', 3: 'Qua', 4: 'Qui', 5: 'Sex', 6: 'Sáb', 7: 'Dom' };

let dbPromise = null;

function openDatabase() {
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

function requestToPromise(request) {
    return new Promise((resolve, reject) => {
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function idbGet(storeName, key) {
    const db = await openDatabase();
    return requestToPromise(db.transaction(storeName, 'readonly').objectStore(storeName).get(key));
}

async function idbPut(storeName, value) {
    const db = await openDatabase();
    return requestToPromise(db.transaction(storeName, 'readwrite').objectStore(storeName).put(value));
}

async function idbDelete(storeName, key) {
    const db = await openDatabase();
    return requestToPromise(db.transaction(storeName, 'readwrite').objectStore(storeName).delete(key));
}

async function getListItems(storeName, listKey) {
    const record = await idbGet(storeName, listKey);
    return record?.items ?? [];
}

async function putList(storeName, listKey, items) {
    return idbPut(storeName, { id: listKey, items, cachedAt: new Date().toISOString() });
}

// IDs de registros criados offline (ainda não sincronizados) usam o prefixo
// `tmp_` e permanecem strings; IDs reais (vindos do banco) são sempre
// numéricos — mesma convenção usada em `offlineStorage.js`.
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

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}

function formatDatePtBr(date) {
    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function todayIsoDayOfWeek() {
    const jsDay = new Date().getDay();
    return jsDay === 0 ? 7 : jsDay;
}

async function enqueueSyncAction(action) {
    return idbPut(STORE_SYNC_QUEUE, {
        occurred_at: new Date().toISOString(),
        queued_at: new Date().toISOString(),
        ...action,
    });
}

// ─── Programas: leitura/escrita otimista (mutações offline) ────────────────

async function mutateProgram(id, mutator) {
    const individual = await idbGet(STORE_PROGRAMS, id);
    const list = await getListItems(STORE_PROGRAMS, PROGRAMS_LIST_KEY);
    const fromList = list.find((program) => program.id === id);
    const base = individual ?? fromList;
    if (!base) return null;

    const updated = mutator({ ...base });

    await idbPut(STORE_PROGRAMS, updated);
    await putList(STORE_PROGRAMS, PROGRAMS_LIST_KEY, list.map((program) => (program.id === id ? updated : program)));

    return updated;
}

async function deleteProgramLocal(id) {
    const list = await getListItems(STORE_PROGRAMS, PROGRAMS_LIST_KEY);
    await idbDelete(STORE_PROGRAMS, id);
    await putList(STORE_PROGRAMS, PROGRAMS_LIST_KEY, list.filter((program) => program.id !== id));
}

async function applyCreateProgram(clientUuid, body) {
    const list = await getListItems(STORE_PROGRAMS, PROGRAMS_LIST_KEY);

    const record = {
        id: `tmp_${clientUuid}`,
        name: body.name,
        description: body.description ?? null,
        is_active: list.length === 0,
        archived_at: null,
        workouts_count: 0,
        workouts: [],
    };

    await idbPut(STORE_PROGRAMS, record);
    await putList(STORE_PROGRAMS, PROGRAMS_LIST_KEY, [record, ...list]);

    return record;
}

async function applyActivateProgram(id) {
    const list = await getListItems(STORE_PROGRAMS, PROGRAMS_LIST_KEY);

    const updatedList = list.map((program) => ({
        ...program,
        is_active: program.id === id,
        archived_at: program.id === id ? null : (program.is_active ? formatDatePtBr(new Date()) : program.archived_at),
    }));

    await putList(STORE_PROGRAMS, PROGRAMS_LIST_KEY, updatedList);

    for (const program of updatedList) {
        const individual = await idbGet(STORE_PROGRAMS, program.id);
        if (individual) {
            await idbPut(STORE_PROGRAMS, { ...individual, is_active: program.is_active, archived_at: program.archived_at });
        }
    }
}

// ─── Treinos: leitura/escrita otimista (mutações offline) ──────────────────

async function mutateWorkout(id, mutator) {
    const individual = await idbGet(STORE_WORKOUTS, id);
    const list = await getListItems(STORE_WORKOUTS, WORKOUTS_LIST_KEY);
    const fromList = list.find((workout) => workout.id === id);
    const base = individual ?? fromList;
    if (!base) return null;

    const updated = mutator({ ...base });

    await idbPut(STORE_WORKOUTS, updated);
    await putList(STORE_WORKOUTS, WORKOUTS_LIST_KEY, list.map((workout) => (workout.id === id ? updated : workout)));

    return updated;
}

async function deleteWorkoutLocal(id) {
    const list = await getListItems(STORE_WORKOUTS, WORKOUTS_LIST_KEY);
    await idbDelete(STORE_WORKOUTS, id);
    await putList(STORE_WORKOUTS, WORKOUTS_LIST_KEY, list.filter((workout) => workout.id !== id));
}

async function flattenExerciseCatalog() {
    const record = await idbGet(STORE_EXERCISES, EXERCISES_CATALOG_KEY);
    const map = {};

    (record?.groups ?? []).forEach((group) => {
        (group.exercises ?? []).forEach((exercise) => {
            map[exercise.id] = exercise;
        });
    });

    return map;
}

async function buildExercisesForWorkout(exercisesInput) {
    const catalogMap = await flattenExerciseCatalog();

    return (exercisesInput ?? []).map((exercise, index) => {
        const meta = catalogMap[exercise.id] ?? {};

        return {
            id: exercise.id,
            name: meta.name ?? '',
            primary_muscle: meta.primary_muscle ?? '',
            target_sets: exercise.target_sets ?? null,
            target_reps: exercise.target_reps ?? null,
            order: exercise.order ?? index,
        };
    });
}

async function applyCreateWorkout(clientUuid, body) {
    const exercises = await buildExercisesForWorkout(body.exercises);
    const muscleGroups = [...new Set(exercises.map((exercise) => exercise.primary_muscle).filter(Boolean))];
    const daysOfWeek = [...(body.days_of_week ?? [])].sort((a, b) => a - b);
    const today = todayIsoDayOfWeek();
    const programId = body.workout_program_id ? normalizeId(body.workout_program_id) : null;

    const record = {
        id: `tmp_${clientUuid}`,
        name: body.name,
        description: body.description ?? null,
        is_active: true,
        workout_program_id: programId,
        program_ids: programId !== null ? [programId] : [],
        exercises_count: exercises.length,
        muscle_groups: muscleGroups,
        days_of_week: daysOfWeek,
        days_of_week_labels: daysOfWeek.map((day) => DAY_SHORT_LABELS[day]),
        is_today: daysOfWeek.includes(today),
        exercises,
    };

    await idbPut(STORE_WORKOUTS, record);
    const list = await getListItems(STORE_WORKOUTS, WORKOUTS_LIST_KEY);
    await putList(STORE_WORKOUTS, WORKOUTS_LIST_KEY, [record, ...list]);

    if (programId !== null) {
        await linkWorkoutToProgramCache(programId, record);
    }

    return record;
}

async function linkWorkoutToProgramCache(programId, workout) {
    await mutateProgram(programId, (program) => {
        const alreadyLinked = program.workouts.some((entry) => entry.id === workout.id);

        return {
            ...program,
            workouts_count: alreadyLinked ? program.workouts_count : program.workouts_count + 1,
            workouts: alreadyLinked
                ? program.workouts
                : [...program.workouts, { id: workout.id, name: workout.name, description: workout.description, exercises_count: workout.exercises_count }],
        };
    });
}

async function unlinkWorkoutFromProgramCache(programId, workoutId) {
    await mutateProgram(programId, (program) => ({
        ...program,
        workouts_count: Math.max(0, program.workouts.filter((entry) => entry.id !== workoutId).length),
        workouts: program.workouts.filter((entry) => entry.id !== workoutId),
    }));
}

// ─── Sessão ativa local (meta: active_session) ─────────────────────────────
//
// Cópia mínima, em JS puro, das mesmas funções de
// `resources/js/Utils/offlineStorage.js` — necessária porque as rotas de
// início/registro/finalização de sessão abaixo (`start_session_fallback`,
// `log_set_fallback`, `finish_session_fallback`) só entram em ação quando o
// FRONT-END decidiu (errado) que estava online e mandou um POST "normal" que
// a rede rejeitou de verdade. Precisam se comportar exatamente como o
// caminho "offline" que o Vue já usa, para que a página re-renderizada logo
// em seguida (via `buildActiveSessionProps`) veja o mesmo estado.

async function setActiveSessionMeta(session) {
    if (!session?.id) return;

    const existing = (await idbGet(STORE_META, 'active_session'))?.value ?? null;
    const merged = existing && String(existing.id) === String(session.id)
        ? { ...existing, ...session }
        : session;

    return idbPut(STORE_META, { key: 'active_session', value: merged });
}

async function clearActiveSessionMeta() {
    return idbDelete(STORE_META, 'active_session');
}

async function recordActiveSessionSetLogMeta(log) {
    const session = (await idbGet(STORE_META, 'active_session'))?.value ?? null;
    if (!session) return;

    const entry = {
        exercise_id: log.exercise_id,
        set_number: log.set_number,
        weight: log.weight === '' || log.weight === undefined ? null : log.weight,
        reps: log.reps === '' || log.reps === undefined ? null : log.reps,
    };

    const logs = Array.isArray(session.set_logs) ? [...session.set_logs] : [];
    const index = logs.findIndex((item) => item.exercise_id === entry.exercise_id && item.set_number === entry.set_number);

    if (index === -1) {
        logs.push(entry);
    } else {
        logs[index] = entry;
    }

    return idbPut(STORE_META, { key: 'active_session', value: { ...session, set_logs: logs } });
}

async function markWorkoutCompletedLocallySW(rawWorkoutId, completedAt) {
    if (!rawWorkoutId) return;

    const id = normalizeId(rawWorkoutId);
    const [individual, list] = await Promise.all([idbGet(STORE_WORKOUTS, id), getListItems(STORE_WORKOUTS, WORKOUTS_LIST_KEY)]);

    if (individual) {
        await idbPut(STORE_WORKOUTS, { ...individual, last_completed_at: completedAt });
    }

    const listIndex = list.findIndex((workout) => workout.id === id);
    if (listIndex !== -1) {
        const updatedList = [...list];
        updatedList[listIndex] = { ...updatedList[listIndex], last_completed_at: completedAt };
        await putList(STORE_WORKOUTS, WORKOUTS_LIST_KEY, updatedList);
    }
}

// ─── Mutações interceptadas offline (POST/PUT/PATCH/DELETE) ────────────────
//
// Cada rota de criação/edição listada aqui, quando a rede falha, é resolvida
// inteiramente a partir do IndexedDB: a mutação é gravada na `sync_queue`
// para ser reenviada ao Laravel quando a conexão voltar (ver
// `resources/js/Composables/useOfflineSync.js`), a mudança é aplicada
// otimisticamente aos dados cacheados, e a resposta é um redirecionamento
// 303 forjado — o mesmo status que o Laravel usaria online (ver
// `Inertia\Middleware::handle()`), que o cliente Inertia segue como uma
// navegação comum, batendo de novo no mini-servidor (GET) abaixo.
const MUTATION_ROUTES = [
    { method: 'POST', pattern: /^\/programs\/?$/, type: 'create_program' },
    { method: 'PUT', pattern: /^\/programs\/([^/]+)\/?$/, type: 'update_program' },
    { method: 'PATCH', pattern: /^\/programs\/([^/]+)\/activate\/?$/, type: 'activate_program' },
    { method: 'PATCH', pattern: /^\/programs\/([^/]+)\/archive\/?$/, type: 'archive_program' },
    { method: 'DELETE', pattern: /^\/programs\/([^/]+)\/?$/, type: 'delete_program' },
    { method: 'POST', pattern: /^\/programs\/([^/]+)\/workouts\/attach\/?$/, type: 'attach_workout' },
    { method: 'DELETE', pattern: /^\/programs\/([^/]+)\/workouts\/([^/]+)\/detach\/?$/, type: 'detach_workout' },
    { method: 'POST', pattern: /^\/workouts\/?$/, type: 'create_workout' },
    { method: 'PUT', pattern: /^\/workouts\/([^/]+)\/?$/, type: 'update_workout' },
    { method: 'PATCH', pattern: /^\/workouts\/([^/]+)\/archive\/?$/, type: 'archive_workout' },
    { method: 'DELETE', pattern: /^\/workouts\/([^/]+)\/?$/, type: 'delete_workout' },

    // Início/registro/finalização de sessão de treino: a tela
    // (`Workouts/Show.vue`, `WorkoutSessions/Active.vue`) decide se enfileira
    // a ação diretamente ou faz um POST "normal" com base em
    // `navigator.onLine` — um sinal que reflete a interface de rede do
    // sistema operacional, NÃO o modo "Offline" da aba Network do DevTools
    // (o jeito mais comum de testar isso), que bloqueia a requisição de
    // verdade sem nunca atualizar essa flag. Essas três rotas cobrem
    // exatamente esse descompasso: se o front-end errar a decisão e mandar
    // um POST "online" que a rede rejeita de verdade, o Service Worker ainda
    // aplica a mesma fila de sincronização de sessão (`/api/sync/
    // workout-sessions`) que o caminho "offline" já usa — em vez de deixar o
    // erro de rede cru estourar até o XHR (`net::ERR_FAILED`), que era
    // exatamente o sintoma relatado ("não consigo iniciar/registrar treino
    // offline", indistinguível de "a navegação inteira quebrou").
    { method: 'POST', pattern: /^\/workouts\/([^/]+)\/start\/?$/, type: 'start_session_fallback' },
    { method: 'POST', pattern: /^\/workout-sessions\/([^/]+)\/sets\/?$/, type: 'log_set_fallback' },
    { method: 'POST', pattern: /^\/workout-sessions\/([^/]+)\/finish\/?$/, type: 'finish_session_fallback' },
];

function matchMutationRoute(method, pathname) {
    for (const route of MUTATION_ROUTES) {
        if (route.method !== method) continue;
        const match = pathname.match(route.pattern);
        if (match) return { ...route, params: match.slice(1) };
    }

    return null;
}

function redirectResponse(location) {
    const target = new URL(location, self.location.origin).toString();
    return new Response(null, { status: 303, headers: { Location: target } });
}

/**
 * Alvo do redirect 303 forjado após uma mutação offline, espelhando o que o
 * controller real faz online — nesta ordem: `return_to` explícito (corpo ou
 * query string) primeiro; senão, o destino "natural" de cada tipo de
 * mutação; só então a página de origem (`request.referrer`) como último
 * recurso.
 *
 * `create_workout` e `create_program` são casos especiais e vêm ANTES do
 * `referrer`, não depois: as duas têm um destino "natural" que replica o
 * `WorkoutController@store`/`WorkoutProgramController@store` reais —
 * `/workouts` (ou a página do programa, quando criada a partir dele) e
 * `/programs`. Usar o referrer para elas quebra especificamente o fluxo da
 * tela dedicada `/workouts/create`: o `referrer` de um fetch disparado
 * DAQUELA página é a própria página, então o usuário criava a ficha e
 * "voltava" para o formulário em branco — indistinguível de "não
 * funcionou". `create_program` não tem este problema hoje (nasce de um modal
 * em `/programs`, cujo referrer já é o destino certo), mas fica bom mesmo
 * assim: nunca soa correto usar a página de criação como fallback do redirect da própria criação.
 */
function resolveMutationRedirectTarget(route, body, requestUrl) {
    const explicit = body.return_to ?? requestUrl.searchParams.get('return_to') ?? null;
    if (explicit) return explicit;

    if (route.type === 'create_workout') {
        return body.workout_program_id ? `/programs/${body.workout_program_id}` : '/workouts';
    }

    if (route.type === 'create_program') {
        return '/programs';
    }

    // Mesmo raciocínio para a tela dedicada `/workouts/{id}/edit`: sem
    // `return_to`, o `WorkoutController@update` real volta para a página da
    // própria ficha (`workouts.show`) — nunca para o formulário de edição,
    // que é justamente o que o referrer aponta aqui.
    if (route.type === 'update_workout') {
        return `/workouts/${route.params[0]}`;
    }

    return null;
}

/**
 * `handleOfflineMutation` nunca pode deixar uma exceção escapar: isso faria
 * a Promise passada a `event.respondWith()` REJEITAR, e um `respondWith()`
 * rejeitado vira um erro de rede genérico (`net::ERR_FAILED`) para quem fez
 * a requisição — a mutação (criar/editar/excluir) simplesmente "não
 * funciona", sem nenhuma pista no console sobre o motivo real. Este
 * wrapper garante duas coisas: (1) o erro real é logado, nomeado com o tipo
 * da mutação; (2) a resposta ainda é um redirect válido para uma página
 * segura, para que a UI não fique travada — mesmo que a mutação em si não
 * tenha sido aplicada.
 */
async function handleOfflineMutation(request, requestUrl, route) {
    try {
        return await applyOfflineMutation(request, requestUrl, route);
    } catch (error) {
        console.error(`[SW] Falha ao aplicar mutação offline "${route.type}":`, error);

        return redirectResponse('/dashboard');
    }
}

async function applyOfflineMutation(request, requestUrl, route) {
    const body = await request.clone().json().catch(() => ({}));
    const uuid = generateUuid();
    const occurredAt = new Date().toISOString();

    // `Referer` está na lista de forbidden header names da Fetch spec — não
    // é exposto via `request.headers.get('Referer')` em nenhum contexto de
    // script, inclusive dentro do próprio Service Worker. O valor equivalente,
    // quando disponível, vem em `request.referrer` (propriedade separada,
    // preenchida pelo navegador com a URL da página que originou o fetch).
    // `let`, não `const`: os três tipos de fallback de sessão (logo abaixo)
    // só sabem o destino correto depois de gerar/consultar dados em tempo de
    // execução (um `client_session_id` novo, ou o `workout_id` da sessão
    // ativa) — cada um reatribui esta variável dentro do próprio `case`.
    let redirectTo = resolveMutationRedirectTarget(route, body, requestUrl)
        ?? (request.referrer || null)
        ?? '/dashboard';

    switch (route.type) {
        case 'create_program': {
            await applyCreateProgram(uuid, body);
            await enqueueSyncAction({
                uuid, type: 'create_program', client_uuid: uuid, occurred_at: occurredAt,
                name: body.name, description: body.description ?? null,
            });
            break;
        }

        case 'update_program': {
            const id = normalizeId(route.params[0]);
            await mutateProgram(id, (program) => ({ ...program, name: body.name, description: body.description ?? null }));
            await enqueueSyncAction({
                uuid, type: 'update_program', occurred_at: occurredAt,
                program_id: route.params[0], name: body.name, description: body.description ?? null,
            });
            break;
        }

        case 'activate_program': {
            const id = normalizeId(route.params[0]);
            await applyActivateProgram(id);
            await enqueueSyncAction({ uuid, type: 'activate_program', occurred_at: occurredAt, program_id: route.params[0] });
            break;
        }

        case 'archive_program': {
            const id = normalizeId(route.params[0]);
            await mutateProgram(id, (program) => ({ ...program, is_active: false, archived_at: formatDatePtBr(new Date()) }));
            await enqueueSyncAction({ uuid, type: 'archive_program', occurred_at: occurredAt, program_id: route.params[0] });
            break;
        }

        case 'delete_program': {
            const id = normalizeId(route.params[0]);
            await deleteProgramLocal(id);
            await enqueueSyncAction({ uuid, type: 'delete_program', occurred_at: occurredAt, program_id: route.params[0] });
            break;
        }

        case 'create_workout': {
            await applyCreateWorkout(uuid, body);
            await enqueueSyncAction({
                uuid, type: 'create_workout', client_uuid: uuid, occurred_at: occurredAt,
                name: body.name, description: body.description ?? null, days_of_week: body.days_of_week ?? [],
                workout_program_id: body.workout_program_id ?? null, exercises: body.exercises ?? [],
            });
            break;
        }

        case 'update_workout': {
            const id = normalizeId(route.params[0]);
            const exercises = await buildExercisesForWorkout(body.exercises);
            const daysOfWeek = [...(body.days_of_week ?? [])].sort((a, b) => a - b);
            const today = todayIsoDayOfWeek();

            await mutateWorkout(id, (workout) => ({
                ...workout,
                name: body.name,
                description: body.description ?? null,
                days_of_week: daysOfWeek,
                days_of_week_labels: daysOfWeek.map((day) => DAY_SHORT_LABELS[day]),
                is_today: daysOfWeek.includes(today),
                exercises,
                exercises_count: exercises.length,
                muscle_groups: [...new Set(exercises.map((exercise) => exercise.primary_muscle).filter(Boolean))],
            }));

            await enqueueSyncAction({
                uuid, type: 'update_workout', occurred_at: occurredAt, workout_id: route.params[0],
                name: body.name, description: body.description ?? null, days_of_week: body.days_of_week ?? [], exercises: body.exercises ?? [],
            });
            break;
        }

        case 'archive_workout': {
            const id = normalizeId(route.params[0]);
            await mutateWorkout(id, (workout) => ({ ...workout, is_active: !workout.is_active }));
            await enqueueSyncAction({ uuid, type: 'archive_workout', occurred_at: occurredAt, workout_id: route.params[0] });
            break;
        }

        case 'delete_workout': {
            const id = normalizeId(route.params[0]);
            await deleteWorkoutLocal(id);
            await enqueueSyncAction({ uuid, type: 'delete_workout', occurred_at: occurredAt, workout_id: route.params[0] });
            break;
        }

        case 'attach_workout': {
            const programId = normalizeId(route.params[0]);
            const workoutIds = body.workout_ids ?? [];

            for (const rawWorkoutId of workoutIds) {
                const workoutId = normalizeId(rawWorkoutId);
                const workout = await mutateWorkout(workoutId, (w) => ({
                    ...w,
                    program_ids: w.program_ids.includes(programId) ? w.program_ids : [...w.program_ids, programId],
                }));

                if (workout) await linkWorkoutToProgramCache(programId, workout);

                await enqueueSyncAction({
                    uuid: generateUuid(), type: 'attach_workout', occurred_at: occurredAt,
                    program_id: route.params[0], workout_id: rawWorkoutId,
                });
            }
            break;
        }

        case 'detach_workout': {
            const programId = normalizeId(route.params[0]);
            const workoutId = normalizeId(route.params[1]);

            await mutateWorkout(workoutId, (workout) => ({
                ...workout,
                program_ids: workout.program_ids.filter((id) => id !== programId),
            }));
            await unlinkWorkoutFromProgramCache(programId, workoutId);

            await enqueueSyncAction({
                uuid, type: 'detach_workout', occurred_at: occurredAt,
                program_id: route.params[0], workout_id: route.params[1],
            });
            break;
        }

        // ─── Fallback de sessão: o front-end mandou um POST "online" (por
        // achar, via `navigator.onLine`, que a rede estava de pé) que a rede
        // rejeitou de verdade. Aplica exatamente o mesmo efeito que o
        // caminho "offline" já teria aplicado — mesma fila
        // (`sync.workout-sessions`), mesmo formato de ação — para que o
        // usuário nunca veja o erro de rede cru.

        case 'start_session_fallback': {
            const workoutId = normalizeId(route.params[0]);
            const clientSessionId = generateUuid();

            const workout = (await idbGet(STORE_WORKOUTS, workoutId))
                ?? (await getListItems(STORE_WORKOUTS, WORKOUTS_LIST_KEY)).find((w) => w.id === workoutId)
                ?? null;

            await enqueueSyncAction({
                uuid, type: 'start_session', client_session_id: clientSessionId,
                occurred_at: occurredAt, workout_id: workoutId,
            });

            await setActiveSessionMeta({
                id: clientSessionId,
                client_session_id: clientSessionId,
                offline_only: true,
                workout_id: workoutId,
                workout_name: workout?.name ?? null,
                started_at: occurredAt,
            });

            await evictInertiaCache();

            redirectTo = `/workout-sessions/${clientSessionId}`;
            break;
        }

        case 'log_set_fallback': {
            const sessionId = normalizeId(route.params[0]);
            const clientSessionId = `session-${sessionId}`;

            await enqueueSyncAction({
                uuid, type: 'log_set', client_session_id: clientSessionId,
                client_set_id: generateUuid(), session_id: sessionId, occurred_at: occurredAt,
                exercise_id: body.exercise_id, set_number: body.set_number,
                weight: body.weight ?? null, reps: body.reps,
            });

            await recordActiveSessionSetLogMeta({
                exercise_id: body.exercise_id, set_number: body.set_number,
                weight: body.weight, reps: body.reps,
            });

            await evictInertiaCache();

            redirectTo = `/workout-sessions/${sessionId}`;
            break;
        }

        case 'finish_session_fallback': {
            const sessionId = normalizeId(route.params[0]);
            const clientSessionId = `session-${sessionId}`;

            const activeSession = (await idbGet(STORE_META, 'active_session'))?.value ?? null;
            const workoutId = activeSession && String(activeSession.id) === String(sessionId)
                ? activeSession.workout_id
                : null;

            await enqueueSyncAction({
                uuid, type: 'finish_session', client_session_id: clientSessionId,
                session_id: sessionId, occurred_at: occurredAt,
            });

            await clearActiveSessionMeta();
            if (workoutId) await markWorkoutCompletedLocallySW(workoutId, occurredAt);
            await evictInertiaCache();

            redirectTo = '/dashboard';
            break;
        }
    }

    return redirectResponse(redirectTo);
}

// ─── Páginas Inertia sintetizadas a partir do IndexedDB (GET) ──────────────
//
// Constrói o mesmo formato de resposta que o Laravel Inertia devolveria para
// aquela página (`{component, props, url, version, ...}`), usando os dados
// que o download inicial (`GET /api/sync/state`, ver
// `resources/js/Utils/syncState.js`) e as mutações offline já deixaram no
// IndexedDB. O Vue nunca sabe que a rede caiu.
const PAGE_ROUTES = [
    { pattern: /^\/(?:dashboard)?\/?$/, component: 'Dashboard', build: buildDashboardProps },
    { pattern: /^\/workouts\/?$/, component: 'Workouts/Index', build: buildWorkoutsIndexProps },
    { pattern: /^\/workouts\/create\/?$/, component: 'Workouts/Create', build: buildWorkoutCreateProps },
    { pattern: /^\/workouts\/([^/]+)\/edit\/?$/, component: 'Workouts/Edit', build: buildWorkoutEditProps },
    { pattern: /^\/workouts\/(?!create\b)([^/]+)\/?$/, component: 'Workouts/Show', build: buildWorkoutShowProps },
    { pattern: /^\/programs\/?$/, component: 'Programs/Index', build: buildProgramsIndexProps },
    { pattern: /^\/programs\/([^/]+)\/?$/, component: 'Programs/Show', build: buildProgramShowProps },
    { pattern: /^\/workout-sessions\/([^/]+)\/?$/, component: 'WorkoutSessions/Active', build: buildActiveSessionProps },
];

function matchPageRoute(pathname) {
    for (const route of PAGE_ROUTES) {
        const match = pathname.match(route.pattern);
        if (match) return { ...route, params: match.slice(1) };
    }

    return null;
}

async function buildDashboardProps() {
    const workouts = await getListItems(STORE_WORKOUTS, WORKOUTS_LIST_KEY);
    const programs = await getListItems(STORE_PROGRAMS, PROGRAMS_LIST_KEY);
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
        // O streak (dias consecutivos treinando) depende do histórico completo
        // de sessões, que não é replicado no IndexedDB — mantém o último valor
        // visível em vez de travar a página; é recalculado assim que a rede volta.
        streak: 0,
        activeWorkouts: activeWorkouts.map((workout) => ({
            id: workout.id,
            name: workout.name,
            exercises_count: workout.exercises_count ?? 0,
            days_of_week_labels: workout.days_of_week_labels ?? [],
        })),
    };
}

async function buildWorkoutsIndexProps() {
    const workouts = await getListItems(STORE_WORKOUTS, WORKOUTS_LIST_KEY);
    const programs = await getListItems(STORE_PROGRAMS, PROGRAMS_LIST_KEY);
    const exercisesCatalogRecord = await idbGet(STORE_EXERCISES, EXERCISES_CATALOG_KEY);
    const today = todayIsoDayOfWeek();
    const activeProgram = programs.find((program) => program.is_active) ?? null;

    return {
        workouts,
        exercisesCatalog: exercisesCatalogRecord?.groups ?? [],
        todayDayOfWeek: today,
        todayDayOfWeekLabel: ['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado', 'Domingo'][today - 1],
        activeProgram: activeProgram ? { id: activeProgram.id, name: activeProgram.name } : null,
    };
}

/**
 * Monta o formulário de criação de ficha (`Workouts/Create`) a partir do
 * catálogo de exercícios cacheado. Sem esta rota, `/workouts/create` não
 * tinha como ser sintetizado offline — o mini-servidor devolvia `null` para
 * qualquer rota desconhecida, e a navegação (de documento ou via XHR do
 * Inertia) caía no JSON de fallback / página cacheada errada, tornando
 * impossível abrir a tela de criação sem internet.
 */
async function buildWorkoutCreateProps() {
    const exercisesCatalogRecord = await idbGet(STORE_EXERCISES, EXERCISES_CATALOG_KEY);

    return {
        exercisesCatalog: exercisesCatalogRecord?.groups ?? [],
    };
}

/**
 * Monta a tela de edição (`Workouts/Edit`) a partir do treino cacheado — a
 * mesma lógica de `buildWorkoutShowProps`, mais o catálogo de exercícios que
 * o formulário precisa para adicionar/trocar exercícios.
 */
async function buildWorkoutEditProps(params) {
    const showProps = await buildWorkoutShowProps(params);
    if (showProps === null) return null;

    const exercisesCatalogRecord = await idbGet(STORE_EXERCISES, EXERCISES_CATALOG_KEY);

    return {
        ...showProps,
        exercisesCatalog: exercisesCatalogRecord?.groups ?? [],
    };
}

async function buildWorkoutShowProps(params) {
    const id = normalizeId(params[0]);
    const direct = await idbGet(STORE_WORKOUTS, id);
    const workout = direct ?? (await getListItems(STORE_WORKOUTS, WORKOUTS_LIST_KEY)).find((w) => w.id === id) ?? null;

    // Sem nenhum dado cacheado para esta ficha específica: sinaliza "sem
    // dados" (`null`) em vez de montar uma página com `workout: null`, que o
    // Vue (`Workouts/Show.vue`) não sabe renderizar (`workout` é obrigatório).
    if (!workout) return null;

    // Defaults antes do spread: um registro gravado por uma versão antiga do
    // app (ou por uma tela que só conhecia parte dos campos) não pode chegar
    // ao Vue sem `muscle_groups`/`exercises` — `Workouts/Show.vue` itera os
    // dois, e um `undefined` aqui aborta o render no meio do card.
    return {
        workout: {
            muscle_groups: [],
            exercises: [],
            days_of_week: [],
            days_of_week_labels: [],
            ...workout,
        },
    };
}

/**
 * Monta a tela de sessão ativa (`WorkoutSessions/Active`) inteiramente a
 * partir do IndexedDB. É isso que faz o botão "Treino em andamento" levar de
 * volta ao treino quando a internet caiu no meio da série: a rota real
 * `/workout-sessions/{id}` existe no servidor, mas sem rede o Laravel nunca
 * responde — aqui ela é remontada com a ficha cacheada, as séries já
 * registradas (fila de sincronização + carimbo na sessão ativa) e o
 * histórico de cargas.
 */
async function buildActiveSessionProps(params) {
    const session = (await idbGet(STORE_META, 'active_session'))?.value ?? null;

    if (!session) return null;
    if (String(session.id) !== String(params[0])) return null;

    const workoutId = normalizeId(session.workout_id);
    const direct = await idbGet(STORE_WORKOUTS, workoutId);
    const workout = direct
        ?? (await getListItems(STORE_WORKOUTS, WORKOUTS_LIST_KEY)).find((item) => item.id === workoutId)
        ?? null;

    if (!workout) return null;

    return {
        session: {
            id: session.id,
            started_at: session.started_at ?? null,
            completed_at: null,
            notes: null,
        },
        workout: {
            id: workout.id,
            name: workout.name,
            description: workout.description ?? null,
        },
        exercises: (workout.exercises ?? []).map((exercise, index) => ({
            id: exercise.id,
            name: exercise.name,
            primary_muscle: exercise.primary_muscle ?? '',
            target_sets: exercise.target_sets ?? 3,
            target_reps: exercise.target_reps ?? '10',
            rest_seconds: exercise.rest_seconds ?? 60,
            notes: exercise.notes ?? null,
            order: exercise.order ?? index,
        })),
        setLogs: await collectSessionSetLogs(session),
        lastLogs: await getCachedLastLogsMap(),
        offlineOnly: session.offline_only === true,
    };
}

/**
 * Une as duas memórias locais de séries desta sessão: o que ainda está na
 * fila esperando sincronizar e o que ficou carimbado no próprio registro da
 * sessão ativa (séries feitas online, ou offline e já sincronizadas — a fila
 * é esvaziada nesse caso). Espelha `getActiveSessionSetLogs()` em
 * `resources/js/Utils/offlineStorage.js`.
 */
async function collectSessionSetLogs(session) {
    const db = await openDatabase();
    const queued = await requestToPromise(
        db.transaction(STORE_SYNC_QUEUE, 'readonly').objectStore(STORE_SYNC_QUEUE).getAll(),
    );

    const clientSessionId = String(session.client_session_id ?? session.id);
    const byPosition = new Map();

    for (const log of Array.isArray(session.set_logs) ? session.set_logs : []) {
        byPosition.set(`${log.exercise_id}-${log.set_number}`, log);
    }

    queued
        .filter((row) => row.type === 'log_set' && String(row.client_session_id) === clientSessionId)
        .sort((a, b) => new Date(a.occurred_at) - new Date(b.occurred_at))
        .forEach((row) => byPosition.set(`${row.exercise_id}-${row.set_number}`, row));

    return [...byPosition.values()].map((log) => ({
        exercise_id: log.exercise_id,
        set_number: log.set_number,
        weight: log.weight ?? null,
        reps: log.reps ?? null,
        rpe: log.rpe ?? null,
        is_warmup: Boolean(log.is_warmup),
        notes: log.notes ?? null,
    }));
}

async function getCachedLastLogsMap() {
    const db = await openDatabase();
    const rows = await requestToPromise(
        db.transaction(STORE_LAST_LOGS, 'readonly').objectStore(STORE_LAST_LOGS).getAll(),
    );

    return rows.reduce((acc, row) => {
        acc[row.exercise_id] = row;
        return acc;
    }, {});
}

async function buildProgramsIndexProps() {
    const programs = await getListItems(STORE_PROGRAMS, PROGRAMS_LIST_KEY);
    return { programs };
}

async function buildProgramShowProps(params) {
    const id = normalizeId(params[0]);
    const direct = await idbGet(STORE_PROGRAMS, id);
    const program = direct ?? (await getListItems(STORE_PROGRAMS, PROGRAMS_LIST_KEY)).find((p) => p.id === id) ?? null;

    if (!program) return null;

    const workouts = await getListItems(STORE_WORKOUTS, WORKOUTS_LIST_KEY);
    const linkedIds = new Set((program.workouts ?? []).map((w) => w.id));

    return {
        program,
        availableWorkouts: workouts
            .filter((workout) => !linkedIds.has(workout.id))
            .map((workout) => ({ id: workout.id, name: workout.name, exercises_count: workout.exercises_count ?? 0 })),
    };
}

/**
 * Props que o `HandleInertiaRequests` compartilha com TODA página
 * autenticada. Sem elas, uma página sintetizada aqui chegava ao Vue sem
 * `auth.user` (nome do usuário vazio na sidebar) e, principalmente, sem
 * `activeWorkoutSession` — que é justamente o que faz o botão flutuante
 * "Treino em andamento" existir. Era por isso que o indicador sumia assim
 * que o usuário navegava offline e reaparecia ao voltar a internet.
 */
async function buildSharedProps() {
    const [user, session, workouts] = await Promise.all([
        idbGet(STORE_META, 'auth_user'),
        idbGet(STORE_META, 'active_session'),
        getListItems(STORE_WORKOUTS, WORKOUTS_LIST_KEY),
    ]);

    const activeSession = session?.value ?? null;

    return {
        auth: { user: user?.value ?? null },
        flash: { success: null, error: null },
        errors: {},
        activeWorkoutSession: activeSession
            ? {
                id: activeSession.id,
                workout_id: activeSession.workout_id ?? null,
                workout_name: activeSession.workout_name ?? null,
                started_at: activeSession.started_at ?? null,
                offline_only: activeSession.offline_only === true,
            }
            : null,
        userWorkoutIds: workouts.filter((workout) => workout.is_active).map((workout) => workout.id),
    };
}

async function buildOfflinePageResponse(request, requestUrl, route) {
    const props = await route.build(route.params);
    if (props === null) return null;

    const version = (await idbGet(STORE_META, 'inertiaVersion'))?.value ?? '';

    const page = {
        component: route.component,
        props: { ...(await buildSharedProps()), ...props },
        url: requestUrl.pathname + requestUrl.search,
        version,
        clearHistory: false,
        encryptHistory: false,
    };

    const isInertiaXhr = request.headers.get('X-Inertia') === 'true';

    if (isInertiaXhr) {
        return new Response(JSON.stringify(page), {
            status: 200,
            headers: { 'Content-Type': 'application/json', 'X-Inertia': 'true' },
        });
    }

    // Navegação de documento completo (F5, digitar a URL) para uma dessas
    // rotas: reaproveita o HTML de qualquer página já cacheada como app
    // shell (mesmos scripts/estilos do Vite) e REESCREVE o `data-page`
    // embutido com a página montada aqui. Sem essa reescrita, o cliente
    // Inertia se rehidratava com o `data-page` congelado de outra página —
    // o usuário dava F5 em `/workout-sessions/12` e caía numa Dashboard
    // velha, sem nenhuma requisição posterior para corrigir o rumo.
    const shellCache = await caches.open(PAGE_CACHE);
    const shellKeys = await shellCache.keys();
    if (shellKeys.length === 0) return null;

    const shellResponse = await shellCache.match(shellKeys[0]);
    if (!shellResponse) return null;

    const html = await shellResponse.text();

    return new Response(replaceInertiaDataPage(html, page), {
        status: 200,
        headers: { 'Content-Type': 'text/html; charset=utf-8' },
    });
}

/**
 * Wrapper de segurança em torno de `buildOfflinePageResponse`. Uma exceção
 * ali dentro (ex.: um `idbGet` falhando porque uma store esperada não existe
 * ainda no IndexedDB deste aparelho, ou um erro num dos `build` de cada
 * rota) NUNCA pode escapar para quem chamou `handleNavigationRequest`/
 * `handleInertiaRequest` — isso rejeitaria a Promise de `event.respondWith()`
 * e viraria um erro de rede genérico (`net::ERR_FAILED`) para a página, sem
 * nenhuma pista no console do que realmente falhou. Aqui o erro é logado
 * (nomeado com a rota) e a chamada degrada para `null`, deixando o restante
 * da cadeia de fallback (cache/app shell) resolver a resposta normalmente.
 */
async function safeBuildOfflinePageResponse(request, requestUrl, route) {
    try {
        return await buildOfflinePageResponse(request, requestUrl, route);
    } catch (error) {
        console.error(`[SW] Falha ao montar a página offline "${route.component}":`, error);

        return null;
    }
}

/**
 * Troca o valor do atributo `data-page` do `<div id="app">` renderizado pelo
 * Blade (`@inertia`) pelo objeto de página montado offline. O valor precisa
 * ser escapado exatamente como o `e()` do Laravel faria, senão as aspas do
 * JSON encerram o atributo e o HTML quebra.
 */
function replaceInertiaDataPage(html, page) {
    const encoded = JSON.stringify(page)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    return html.replace(/data-page="[^"]*"/, `data-page="${encoded}"`);
}

// ─── Estratégias de rede genéricas (mantidas da versão anterior) ───────────

// Network-first: tenta a rede e atualiza o cache a cada sucesso; se a rede
// falhar (ou já sabemos, via navigator.onLine, que estamos offline), cai
// direto para a última resposta em cache daquela mesma requisição.
async function networkFirst(request, cacheName) {
    const cache = await caches.open(cacheName);

    try {
        const networkResponse = await fetch(request);
        cache.put(request, networkResponse.clone());
        return networkResponse;
    } catch (error) {
        // Offline ou timeout: cai para a última resposta conhecida daquela
        // requisição. Primeiro no cache específico da estratégia e, na falta
        // dele, em qualquer cache do Service Worker (`caches.match` global).
        const cachedResponse = await cache.match(request);
        if (cachedResponse) return cachedResponse;

        const fallbackResponse = await caches.match(request);
        if (fallbackResponse) return fallbackResponse;

        throw error;
    }
}

/**
 * Mesma ideia de `networkFirst`, mas para rotas que o mini-servidor
 * (IndexedDB) sabe reconstruir (`PAGE_ROUTES`): quando a rede falha de
 * verdade, o IndexedDB tem prioridade sobre qualquer resposta HTTP cacheada
 * — que pode ser de ANTES da mutação mais recente (ex.: uma ficha criada
 * offline há 5 segundos não existe na resposta de `/workouts` cacheada
 * ontem).
 *
 * Corrigido para NUNCA depender de `navigator.onLine` para decidir a ordem
 * de prioridade: o modo "Offline" da aba Network do DevTools — o jeito mais
 * comum de testar isso — bloqueia requisições de verdade, mas NUNCA muda
 * essa flag (ela só reflete o estado da interface de rede do sistema
 * operacional). Depender dela aqui fazia o Service Worker sempre tentar a
 * rede primeiro, falhar, e então servir o cache HTTP desatualizado — porque
 * era exatamente isso que `networkFirst()` fazia no `catch` — sem nunca
 * chegar a reconstruir a página a partir do IndexedDB. O sintoma: uma ficha
 * criada offline só aparecia depois que a internet voltava de verdade.
 */
async function networkFirstWithOfflineSynthesis(request, requestUrl, cacheName, pageRoute) {
    const cache = await caches.open(cacheName);

    try {
        const networkResponse = await fetch(request);
        cache.put(request, networkResponse.clone());
        return networkResponse;
    } catch (error) {
        if (pageRoute) {
            const offlinePage = await safeBuildOfflinePageResponse(request, requestUrl, pageRoute);
            if (offlinePage) return offlinePage;
        }

        const cachedResponse = await cache.match(request);
        if (cachedResponse) return cachedResponse;

        throw error;
    }
}

// Cache-first: usada para os assets fixos do app shell (manifest, ícones),
// cujo caminho nunca muda entre deploys.
async function cacheFirst(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cachedResponse = await cache.match(request);
    if (cachedResponse) return cachedResponse;

    const networkResponse = await fetch(request);
    cache.put(request, networkResponse.clone());
    return networkResponse;
}

// Stale-while-revalidate: usada para os pacotes compilados pelo Vite
// (`/build/assets/*`). O nome de cada chunk muda a cada build (hash de
// conteúdo no nome do arquivo), então um cache-first "cego" falha sempre que
// o app é atualizado e o Service Worker antigo ainda está ativo pedindo um
// chunk que já não existe mais no cache — a resposta em cache (quando existe)
// é devolvida imediatamente, e a rede só é consultada em paralelo, em
// segundo plano, para manter o cache atualizado. Se a rede falhar (offline),
// o erro nunca escapa como rejeição não tratada — é exatamente isso que
// produzia `ERR_INTERNET_DISCONNECTED` no console antes desta mudança.
async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cachedResponse = await cache.match(request);

    const revalidate = fetch(request)
        .then((networkResponse) => {
            if (networkResponse && networkResponse.ok) {
                cache.put(request, networkResponse.clone());
            }
            return networkResponse;
        })
        .catch(() => null);

    if (cachedResponse) {
        return cachedResponse;
    }

    const networkResponse = await revalidate;
    if (networkResponse) {
        return networkResponse;
    }

    // Nunca esteve em cache e a rede falhou: não há nada para servir, mas a
    // resposta precisa ser uma `Response` sintética controlada — nunca a
    // rejeição crua do `fetch()` — para que o carregamento do chunk falhe de
    // forma previsível em vez de estourar um erro de rede no console.
    return new Response(null, { status: 504, statusText: 'Asset indisponível offline' });
}

// Navegação de documento completo (F5, digitar a URL, abrir um link externo):
// network-first; se a rede falhar, tenta montar a página via mini-servidor
// (dados reais do IndexedDB) e, na ausência de dados para aquela rota
// específica, cai para a última página cacheada como app shell.
async function handleNavigationRequest(request, requestUrl) {
    const pageRoute = matchPageRoute(requestUrl.pathname);

    try {
        return await networkFirstWithOfflineSynthesis(request, requestUrl, PAGE_CACHE, pageRoute);
    } catch (error) {
        const cache = await caches.open(PAGE_CACHE);

        const cachedDashboard = await cache.match('/dashboard');
        if (cachedDashboard) return cachedDashboard;

        const cachedKeys = await cache.keys();
        if (cachedKeys.length > 0) return cache.match(cachedKeys[0]);

        return new Response(OFFLINE_FALLBACK_HTML, {
            headers: { 'Content-Type': 'text/html; charset=utf-8' },
        });
    }
}

// Requisições Inertia (X-Inertia) ou qualquer outra chamada GET que espera
// JSON: network-first; se a rede falhar, tenta montar a resposta via
// mini-servidor (dados reais do IndexedDB) para as rotas conhecidas
// (Dashboard, Treinos, Programas). Na ausência de uma rota reconhecida ou de
// dados para montá-la, cai para o cache daquela URL exata e, por fim, nunca
// deixa a Promise do Axios rejeitar com erro de rede — devolve um JSON de
// fallback sinalizado por um cabeçalho, que o bootstrap do app
// (`resources/js/bootstrap.js`) trata recarregando a página, caindo na
// navegação de documento completo acima.
async function handleInertiaRequest(request, requestUrl) {
    const pageRoute = matchPageRoute(requestUrl.pathname);

    try {
        return await networkFirstWithOfflineSynthesis(request, requestUrl, INERTIA_CACHE, pageRoute);
    } catch (error) {
        return new Response(JSON.stringify({}), {
            status: 200,
            headers: {
                'Content-Type': 'application/json',
                'X-Inertia': 'true',
                'X-Inertia-Offline-Fallback': 'true',
            },
        });
    }
}

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const requestUrl = new URL(request.url);

    // Extensões do Chrome injetam scripts que disparam suas próprias
    // requisições (`chrome-extension://...`, CDNs de terceiros etc.). Sem
    // este bypass, esse tráfego cairia nas estratégias abaixo e poderia
    // quebrar o carregamento do app; ignorando aqui, o navegador trata a
    // requisição normalmente, fora do escopo deste Service Worker.
    if (requestUrl.origin !== self.location.origin) {
        return;
    }

    if (request.method !== 'GET') {
        const mutationRoute = matchMutationRoute(request.method, requestUrl.pathname);
        if (!mutationRoute) return;

        const requestForFallback = request.clone();

        event.respondWith(
            fetch(request).catch(() => handleOfflineMutation(requestForFallback, requestUrl, mutationRoute))
        );
        return;
    }

    // Pacotes compilados do Vite: stale-while-revalidate (ver comentário na
    // definição da função acima).
    if (requestUrl.pathname.startsWith('/build/assets/')) {
        event.respondWith(staleWhileRevalidate(request, STATIC_ASSETS_CACHE));
        return;
    }

    // Requisições XHR do Inertia (navegação entre páginas do SPA sem reload)
    // ou qualquer outra chamada GET que espera JSON como resposta.
    const isInertiaRequest = request.headers.get('X-Inertia') === 'true';
    const isJsonRequest = (request.headers.get('Accept') ?? '').includes('application/json');

    if (isInertiaRequest) {
        event.respondWith(handleInertiaRequest(request, requestUrl));
        return;
    }

    // Chamadas JSON que NÃO são navegação do Inertia (`/api/sync/state`, as
    // sugestões de sobrecarga): rede e só rede.
    //
    // Nada de cache aqui, e isso é deliberado: `/api/sync/state` é a foto
    // completa do estado do usuário, e `saveFullSyncState()` SOBRESCREVE o
    // IndexedDB com o que receber. Servir uma foto antiga do cache apagaria
    // fichas criadas offline que ainda não subiram. E nada de JSON de
    // fallback: era ele que fazia o interceptor do `bootstrap.js` recarregar
    // a página em looping quando `navigator.onLine` mentia. A falha vira um
    // 504 sintético só para não sujar o console com erro de rede cru — quem
    // chama já sabe se degradar sozinho.
    if (isJsonRequest) {
        event.respondWith(
            fetch(request).catch(
                () => new Response(null, { status: 504, statusText: 'Indisponível offline' }),
            ),
        );
        return;
    }

    // Navegação de documento completo.
    if (request.mode === 'navigate') {
        event.respondWith(handleNavigationRequest(request, requestUrl));
        return;
    }

    // Demais assets do app shell (manifest, ícones).
    if (APP_SHELL_ASSETS.includes(requestUrl.pathname)) {
        event.respondWith(cacheFirst(request, APP_SHELL_CACHE));
    }
});
