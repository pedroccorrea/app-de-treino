const CACHE_VERSION = 'v4';
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
            .then((cache) => cache.addAll(APP_SHELL_ASSETS))
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
                if (!db.objectStoreNames.contains('cached_last_logs')) {
                    db.createObjectStore('cached_last_logs', { keyPath: 'exercise_id' });
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

async function handleOfflineMutation(request, requestUrl, route) {
    const body = await request.clone().json().catch(() => ({}));
    const uuid = generateUuid();
    const occurredAt = new Date().toISOString();

    // `Referer` está na lista de forbidden header names da Fetch spec — não
    // é exposto via `request.headers.get('Referer')` em nenhum contexto de
    // script, inclusive dentro do próprio Service Worker. O valor equivalente,
    // quando disponível, vem em `request.referrer` (propriedade separada,
    // preenchida pelo navegador com a URL da página que originou o fetch).
    const redirectTo = body.return_to
        ?? requestUrl.searchParams.get('return_to')
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
    { pattern: /^\/workouts\/(?!create\b)([^/]+)\/?$/, component: 'Workouts/Show', build: buildWorkoutShowProps },
    { pattern: /^\/programs\/?$/, component: 'Programs/Index', build: buildProgramsIndexProps },
    { pattern: /^\/programs\/([^/]+)\/?$/, component: 'Programs/Show', build: buildProgramShowProps },
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

async function buildWorkoutShowProps(params) {
    const id = normalizeId(params[0]);
    const direct = await idbGet(STORE_WORKOUTS, id);
    const workout = direct ?? (await getListItems(STORE_WORKOUTS, WORKOUTS_LIST_KEY)).find((w) => w.id === id) ?? null;

    // Sem nenhum dado cacheado para esta ficha específica: sinaliza "sem
    // dados" (`null`) em vez de montar uma página com `workout: null`, que o
    // Vue (`Workouts/Show.vue`) não sabe renderizar (`workout` é obrigatório).
    return workout ? { workout } : null;
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

async function buildOfflinePageResponse(request, requestUrl, route) {
    const props = await route.build(route.params);
    if (props === null) return null;

    const version = (await idbGet(STORE_META, 'inertiaVersion'))?.value ?? '';

    const page = {
        component: route.component,
        props,
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
    // shell (o cliente Inertia se rehidrata a partir do `data-page` embutido
    // nela) — o conteúdo real chega em seguida pela visita XHR que o próprio
    // Inertia dispara ao montar.
    const shellCache = await caches.open(PAGE_CACHE);
    const shellKeys = await shellCache.keys();
    if (shellKeys.length > 0) return shellCache.match(shellKeys[0]);

    return null;
}

// ─── Estratégias de rede genéricas (mantidas da versão anterior) ───────────

// Network-first: tenta a rede e atualiza o cache a cada sucesso; se a rede
// falhar (ou já sabemos, via navigator.onLine, que estamos offline), cai
// direto para a última resposta em cache daquela mesma requisição.
async function networkFirst(request, cacheName) {
    const cache = await caches.open(cacheName);

    if (!self.navigator.onLine) {
        const cachedResponse = await cache.match(request);
        if (cachedResponse) return cachedResponse;
    }

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
    try {
        return await networkFirst(request, PAGE_CACHE);
    } catch (error) {
        const pageRoute = matchPageRoute(requestUrl.pathname);
        if (pageRoute) {
            const offlinePage = await buildOfflinePageResponse(request, requestUrl, pageRoute);
            if (offlinePage) return offlinePage;
        }

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
    try {
        return await networkFirst(request, INERTIA_CACHE);
    } catch (error) {
        const pageRoute = matchPageRoute(requestUrl.pathname);
        if (pageRoute) {
            const offlinePage = await buildOfflinePageResponse(request, requestUrl, pageRoute);
            if (offlinePage) return offlinePage;
        }

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

    if (isInertiaRequest || isJsonRequest) {
        event.respondWith(handleInertiaRequest(request, requestUrl));
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
