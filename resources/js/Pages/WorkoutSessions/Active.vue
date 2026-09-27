<script setup>
import Sidebar from '@/Components/Navigation/Sidebar.vue';
import ExerciseActiveCard from '@/Components/WorkoutSessions/ExerciseActiveCard.vue';
import ExerciseOverviewDrawer from '@/Components/WorkoutSessions/ExerciseOverviewDrawer.vue';
import ExerciseStepper from '@/Components/WorkoutSessions/ExerciseStepper.vue';
import FinishWorkoutModal from '@/Components/WorkoutSessions/FinishWorkoutModal.vue';
import NavigationGuardModal from '@/Components/WorkoutSessions/NavigationGuardModal.vue';
import RestTimerOverlay from '@/Components/WorkoutSessions/RestTimerOverlay.vue';
import SessionHeader from '@/Components/WorkoutSessions/SessionHeader.vue';
import { useNowTicker } from '@/Composables/useNowTicker';
import { useOfflineSync } from '@/Composables/useOfflineSync';
import { useRestAlarm } from '@/Composables/useRestAlarm';
import { useWakeLock } from '@/Composables/useWakeLock';
import { cacheLastLogs, cacheWorkout, generateUuid, recordActiveSessionSetLog } from '@/Utils/offlineStorage';
import { playRestAlarm, stopRestAlarm, unlockRestAlarm } from '@/Utils/restAlarm';
import { getKeepScreenOn } from '@/Utils/workoutPreferences';
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

// ─── Relógio compartilhado (delta de Date.now(), resiliente a segundo plano) ──
const { now } = useNowTicker();
const { requestWakeLock, releaseWakeLock } = useWakeLock();
const { armRestAlarm, disarmRestAlarm } = useRestAlarm();
// Agrupa esta sessão (já real no banco) na fila de sincronização caso a
// conexão caia no meio do treino — nesse cenário `client_session_id` é só
// uma etiqueta de agrupamento, já que `session_id` real é sempre enviado.
const { isOnline, queueAction, startActiveSessionLocally, finishActiveSessionLocally, notifySessionChanged } = useOfflineSync();

// ─── Menu lateral (navegação livre sem interromper a sessão) ──────────────────
const sidebarOpen = ref(false);

const logout = () => {
    router.post(route('logout'));
};

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps({
    session: { type: Object, required: true },
    workout: { type: Object, required: true },
    exercises: { type: Array, required: true },
    setLogs: { type: Array, default: () => [] },
    lastLogs: { type: Object, default: () => ({}) },
    // true quando esta sessão nasceu 100% offline (nenhuma WorkoutSession
    // real existe no servidor ainda — `session.id` é só o `client_session_id`
    // gerado no dispositivo). Nesse modo toda mutação vai para a fila de
    // sincronização, mesmo que a conexão volte no meio do treino: só a
    // sincronização em lote sabe criar a sessão real a partir do zero.
    offlineOnly: { type: Boolean, default: false },
});

// ─── Sorted exercises ─────────────────────────────────────────────────────────
const sortedExercises = computed(() =>
    [...props.exercises].sort((a, b) => (a.order ?? 0) - (b.order ?? 0)),
);

// ─── Focus state ──────────────────────────────────────────────────────────────
const currentIndex = ref(0);
const currentExercise = computed(() => sortedExercises.value[currentIndex.value] ?? null);
const totalExercises = computed(() => sortedExercises.value.length);

// ─── AI overload suggestion for the exercise in focus ──────────────────────────
// Fetched in the background after the screen renders, so a slow or failing
// Gemini call never blocks opening the session — the user can start Série 1
// immediately, and cards update silently once the suggestions arrive.
// dismissedSuggestions[exerciseId] = true once the user closes the card for it.
const dismissedSuggestions = ref({});
const overloadSuggestions = ref([]);
const overloadStatus = ref('loading'); // 'loading' | 'ready' | 'error'

const fetchOverloadSuggestions = async () => {
    overloadStatus.value = 'loading';
    try {
        const response = await fetch(route('workout-sessions.overload-suggestions', props.session.id), {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) throw new Error('overload-suggestions request failed');
        const data = await response.json();
        overloadSuggestions.value = Array.isArray(data.suggestions) ? data.suggestions : [];
        overloadStatus.value = 'ready';
    } catch (_) {
        overloadSuggestions.value = [];
        overloadStatus.value = 'error';
    }
};

const currentOverloadSuggestion = computed(() => {
    if (dismissedSuggestions.value[currentExercise.value?.id]) return null;
    return overloadSuggestions.value.find((s) => s.exercise_id === currentExercise.value?.id) ?? null;
});

const dismissOverloadSuggestion = () => {
    if (currentExercise.value) dismissedSuggestions.value[currentExercise.value.id] = true;
};

// Applies a suggestion straight to the set currently "running" (if any).
const applyOverloadSuggestion = ({ load, reps }) => {
    const exercise = currentExercise.value;
    if (!exercise) return;

    const runningSet = currentSets.value.find((s) => s.state === 'running');
    if (!runningSet) return;

    setInputs.value[exercise.id][runningSet.number] = { weight: load, reps };
};

// ─── Drawer (exercise overview) ───────────────────────────────────────────────
const showDrawer = ref(false);

// ─── Navigation guard modal ───────────────────────────────────────────────────
const showNavGuard = ref(false);
const pendingNavIndex = ref(null);

// ─── Finish modal ─────────────────────────────────────────────────────────────
const showFinishModal = ref(false);
const finishing = ref(false);

// ─── Exercise timer (runs while a set is "executing") ─────────────────────────
// Timer starts when user clicks "▶ Iniciar Série X", stops when set is saved.
// Baseado em delta de timestamp: guardamos apenas o instante de início e
// recalculamos os segundos decorridos a cada tick do relógio compartilhado,
// em vez de incrementar um contador (que atrasa em segundo plano).
const exerciseTimerActive = ref(false);
const exerciseTimerStartedAt = ref(null);

const startExerciseTimer = () => {
    exerciseTimerActive.value = true;
    exerciseTimerStartedAt.value = Date.now();
};

const stopExerciseTimer = () => {
    exerciseTimerActive.value = false;
};

const resetExerciseTimer = () => {
    stopExerciseTimer();
    exerciseTimerStartedAt.value = null;
};

const exerciseTimerSeconds = computed(() => {
    if (!exerciseTimerActive.value || !exerciseTimerStartedAt.value) return 0;
    return Math.max(0, Math.floor((now.value - exerciseTimerStartedAt.value) / 1000));
});

const exerciseTimerDisplay = computed(() => {
    const s = exerciseTimerSeconds.value;
    const m = Math.floor(s / 60);
    const sec = s % 60;
    return `${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
});

// ─── Workout elapsed timer ────────────────────────────────────────────────────
// Também baseado em delta: recalculado a cada tick contra `session.started_at`,
// então nunca atrasa mesmo que o app fique minutos em segundo plano.
const sessionStartedAtMs = props.session.started_at
    ? new Date(props.session.started_at).getTime()
    : Date.now();

const elapsedSeconds = computed(() =>
    Math.max(0, Math.floor((now.value - sessionStartedAtMs) / 1000)),
);

const elapsedDisplay = computed(() => {
    const s = elapsedSeconds.value;
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    return h > 0
        ? `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`
        : `${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
});

// ─── Set States: waiting | running | done ─────────────────────────────────────
// setState[exerciseId][setNumber] = 'waiting' | 'running' | 'done'
const setStates = ref({});

// setInputs[exerciseId][setNumber] = { weight, reps }
const setInputs = ref({});

// Which sets have been persisted to backend
const savedSets = ref({}); // { [exerciseId]: Set<setNumber> }

// Extra sets added dynamically
const extraSets = ref({});

const initSetsForExercise = (exercise) => {
    const exId = exercise.id;

    if (!setInputs.value[exId]) setInputs.value[exId] = {};
    if (!savedSets.value[exId]) savedSets.value[exId] = new Set();
    if (!setStates.value[exId]) setStates.value[exId] = {};
    if (!extraSets.value[exId]) extraSets.value[exId] = 0;

    const targetSets = exercise.target_sets ?? 3;
    const last = props.lastLogs?.[exId];

    // Pre-fill from persisted setLogs
    for (const log of props.setLogs.filter((l) => l.exercise_id === exId)) {
        setInputs.value[exId][log.set_number] = {
            weight: log.weight ?? '',
            reps: log.reps ?? '',
        };
        savedSets.value[exId].add(log.set_number);
        setStates.value[exId][log.set_number] = 'done';
    }

    // Série 1: herda carga/reps da última vez que o exercício foi feito.
    // Sem histórico (exercício novo), começa zerada na meta de repetições.
    if (!setInputs.value[exId][1]) {
        setInputs.value[exId][1] = {
            weight: last?.weight ?? 0,
            reps: last?.reps ?? exercise.target_reps ?? '',
        };
    }
    if (!setStates.value[exId][1]) {
        setStates.value[exId][1] = 'waiting';
    }

    // Séries seguintes: herdam da série imediatamente anterior desta mesma
    // sessão. Aqui é só o valor inicial (o histórico); completeSet() reescreve
    // esse valor com o que foi de fato registrado assim que a série anterior
    // é concluída.
    for (let n = 2; n <= targetSets; n++) {
        if (!setInputs.value[exId][n]) {
            const previous = setInputs.value[exId][n - 1];
            setInputs.value[exId][n] = {
                weight: previous?.weight ?? last?.weight ?? 0,
                reps: previous?.reps ?? last?.reps ?? exercise.target_reps ?? '',
            };
        }
        if (!setStates.value[exId][n]) {
            setStates.value[exId][n] = 'waiting';
        }
    }
};

// ─── Helpers ──────────────────────────────────────────────────────────────────
const totalSetsForExercise = (exercise) =>
    (exercise.target_sets ?? 3) + (extraSets.value[exercise.id] ?? 0);

const isSetSaved = (exId, n) => savedSets.value[exId]?.has(n) ?? false;

const getSetState = (exId, n) => setStates.value[exId]?.[n] ?? 'waiting';

const isExerciseDone = (exercise) => {
    const total = totalSetsForExercise(exercise);
    if (total === 0) return false;
    for (let n = 1; n <= total; n++) {
        if (!isSetSaved(exercise.id, n)) return false;
    }
    return true;
};

// Returns next set number that is in "waiting" state
const nextWaitingSet = (exercise) => {
    const exId = exercise.id;
    const total = totalSetsForExercise(exercise);
    for (let n = 1; n <= total; n++) {
        if (getSetState(exId, n) === 'waiting') return n;
    }
    return null;
};

// Auto-starts the next pending set of an exercise (no manual "Iniciar Série" click needed).
// No-op if the exercise is already finished or already has a set running.
const autoStartNextSet = (exercise) => {
    if (!exercise || isExerciseDone(exercise)) return;

    const exId = exercise.id;
    const alreadyRunning = Object.values(setStates.value[exId] ?? {}).includes('running');
    if (alreadyRunning) return;

    const nextSet = nextWaitingSet(exercise);
    if (nextSet !== null) {
        startSet(exercise, nextSet);
    }
};

// ─── Progress ─────────────────────────────────────────────────────────────────
const totalPlannedSets = computed(() =>
    sortedExercises.value.reduce((sum, ex) => sum + totalSetsForExercise(ex), 0),
);

const totalDoneSets = computed(() =>
    sortedExercises.value.reduce((sum, ex) => sum + (savedSets.value[ex.id]?.size ?? 0), 0),
);

const progressPercent = computed(() =>
    totalPlannedSets.value > 0
        ? Math.round((totalDoneSets.value / totalPlannedSets.value) * 100)
        : 0,
);

// ─── Total volume ─────────────────────────────────────────────────────────────
// Offline o servidor nunca devolve `setLogs` atualizados, então o volume
// precisa de uma cópia local que cresce a cada série concluída — sem ela o
// rodapé ficava congelado em "0 kg" durante todo um treino sem internet.
const localSetLogs = ref([...props.setLogs]);

const upsertLocalSetLog = (exerciseId, setNumber, inputs) => {
    const entry = {
        exercise_id: exerciseId,
        set_number: setNumber,
        weight: inputs.weight === '' || inputs.weight === undefined ? null : inputs.weight,
        reps: inputs.reps === '' || inputs.reps === undefined ? null : inputs.reps,
    };

    const index = localSetLogs.value.findIndex(
        (log) => log.exercise_id === exerciseId && log.set_number === setNumber,
    );

    if (index === -1) {
        localSetLogs.value.push(entry);
    } else {
        localSetLogs.value[index] = entry;
    }
};

const totalVolume = computed(() =>
    localSetLogs.value.reduce((sum, log) => {
        if (log.weight && log.reps) return sum + parseFloat(log.weight) * parseInt(log.reps);
        return sum;
    }, 0),
);

const volumeDisplay = computed(() =>
    totalVolume.value >= 1000
        ? (totalVolume.value / 1000).toFixed(2) + 't'
        : totalVolume.value.toFixed(0) + 'kg',
);

// ─── View models for the "dumb" presentational components ────────────────────
const stepperExercises = computed(() =>
    sortedExercises.value.map((ex) => ({ id: ex.id, name: ex.name, done: isExerciseDone(ex) })),
);

const drawerExercises = computed(() =>
    sortedExercises.value.map((ex) => ({
        id: ex.id,
        name: ex.name,
        primary_muscle: ex.primary_muscle,
        done: isExerciseDone(ex),
        doneCount: savedSets.value[ex.id]?.size ?? 0,
        totalSets: totalSetsForExercise(ex),
    })),
);

const currentSets = computed(() => {
    if (!currentExercise.value) return [];
    const total = totalSetsForExercise(currentExercise.value);
    return Array.from({ length: total }, (_, i) => {
        const number = i + 1;
        return { number, state: getSetState(currentExercise.value.id, number) };
    });
});

const currentLastLog = computed(() => props.lastLogs?.[currentExercise.value?.id] ?? null);

const isLastExercise = computed(() => currentIndex.value === totalExercises.value - 1);

const canAddExtraSet = computed(() =>
    currentExercise.value
        ? totalSetsForExercise(currentExercise.value) < 10 && !nextWaitingSet(currentExercise.value)
        : false,
);

const currentNextWaitingSet = computed(() =>
    currentExercise.value ? nextWaitingSet(currentExercise.value) : null,
);

const currentTotalSets = computed(() =>
    currentExercise.value ? totalSetsForExercise(currentExercise.value) : null,
);

// ─── Start a set (waiting → running) ─────────────────────────────────────────
const startSet = (exercise, setNumber) => {
    const exId = exercise.id;
    // Mark any previously "running" sets back to waiting
    for (const n in setStates.value[exId] ?? {}) {
        if (setStates.value[exId][n] === 'running') {
            setStates.value[exId][n] = 'waiting';
        }
    }
    setStates.value[exId][setNumber] = 'running';
    startExerciseTimer();
};

// ─── Complete a set (running → done) ─────────────────────────────────────────
const savingSet = ref(null);

// Marca a série como concluída na UI local e agenda o descanso — usado tanto
// pelo caminho online (após confirmação do servidor) quanto pelo offline
// (otimista, já que a mutação só será confirmada quando a fila sincronizar).
const markSetCompleteLocally = (exercise, setNumber, inputs) => {
    const exId = exercise.id;
    if (!savedSets.value[exId]) savedSets.value[exId] = new Set();
    savedSets.value[exId].add(setNumber);
    setStates.value[exId][setNumber] = 'done';

    // Espelha a série no registro local da sessão ativa (IndexedDB) — nos
    // dois caminhos, online e offline. É esse carimbo que permite sair da
    // tela do treino e voltar depois com tudo marcado, sem depender de uma
    // resposta do servidor que pode não existir.
    upsertLocalSetLog(exId, setNumber, inputs);
    recordActiveSessionSetLog({
        exercise_id: exId,
        set_number: setNumber,
        weight: inputs.weight,
        reps: inputs.reps,
    });

    const nextSetNumber = setNumber + 1;
    if (!isSetSaved(exId, nextSetNumber)) {
        setInputs.value[exId][nextSetNumber] = {
            weight: inputs.weight !== '' && inputs.weight !== null ? inputs.weight : 0,
            reps: inputs.reps,
        };
    }

    stopExerciseTimer();
    startRest(exercise.rest_seconds ?? 60);
};

// Sessão sem WorkoutSession real no servidor ainda (nasceu offline): toda
// mutação segue para a fila mesmo que a conexão volte no meio do treino,
// já que só a sincronização em lote sabe criar essa sessão a partir do zero.
const clientSessionId = computed(() =>
    props.offlineOnly ? props.session.id : `session-${props.session.id}`,
);

const completeSet = async (exercise, setNumber) => {
    // Ainda dentro do toque em "Concluir série": é a chance de liberar o
    // áudio antes do descanso — `startRest()` só roda depois do servidor
    // responder, fora do gesto, e aí o celular recusaria o play().
    unlockRestAlarm();

    const exId = exercise.id;
    const inputs = setInputs.value[exId]?.[setNumber] ?? {};
    savingSet.value = `${exId}-${setNumber}`;

    if (props.offlineOnly || !isOnline.value) {
        await queueAction({
            type: 'log_set',
            client_session_id: clientSessionId.value,
            client_set_id: generateUuid(),
            session_id: props.offlineOnly ? null : props.session.id,
            occurred_at: new Date().toISOString(),
            exercise_id: exId,
            set_number: setNumber,
            weight: inputs.weight !== '' ? inputs.weight : null,
            reps: inputs.reps,
        });
        markSetCompleteLocally(exercise, setNumber, inputs);
        notifySessionChanged();
        savingSet.value = null;
        return;
    }

    router.post(
        route('workout-sessions.sets.store', props.session.id),
        {
            exercise_id: exId,
            set_number: setNumber,
            weight: inputs.weight !== '' ? inputs.weight : null,
            reps: inputs.reps,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                markSetCompleteLocally(exercise, setNumber, inputs);
                notifySessionChanged();
                savingSet.value = null;
            },
            onError: () => {
                savingSet.value = null;
            },
        },
    );
};

// ─── Quick weight/reps adjustments ───────────────────────────────────────────
const adjustWeight = (exercise, setNumber, delta) => {
    const exId = exercise.id;
    const current = parseFloat(setInputs.value[exId]?.[setNumber]?.weight) || 0;
    setInputs.value[exId][setNumber].weight = Math.max(0, current + delta);
};

const adjustReps = (exercise, setNumber, delta) => {
    const exId = exercise.id;
    const current = parseInt(setInputs.value[exId]?.[setNumber]?.reps) || 0;
    setInputs.value[exId][setNumber].reps = Math.max(1, current + delta);
};

// ─── Direct weight/reps entry (tap-to-edit on the load/reps hero) ───────────
const setWeight = (exercise, setNumber, value) => {
    const exId = exercise.id;
    setInputs.value[exId][setNumber].weight = Math.max(0, value);
};

const setReps = (exercise, setNumber, value) => {
    const exId = exercise.id;
    setInputs.value[exId][setNumber].reps = Math.max(1, value);
};

// ─── Add extra set ────────────────────────────────────────────────────────────
const addExtraSet = (exercise) => {
    const exId = exercise.id;
    extraSets.value[exId] = (extraSets.value[exId] ?? 0) + 1;
    const newSetNum = totalSetsForExercise(exercise);
    const previous = setInputs.value[exId]?.[newSetNum - 1];
    const last = props.lastLogs?.[exId];
    setInputs.value[exId][newSetNum] = {
        weight: previous?.weight ?? last?.weight ?? 0,
        reps: previous?.reps ?? last?.reps ?? exercise.target_reps ?? '',
    };
    setStates.value[exId][newSetNum] = 'waiting';
    autoStartNextSet(exercise);
};

// ─── Navigation ───────────────────────────────────────────────────────────────
const tryNavigateTo = (index) => {
    if (index < 0 || index >= totalExercises.value) return;
    // Guard: if exercise timer is running (a set is in "running" state)
    if (exerciseTimerActive.value) {
        pendingNavIndex.value = index;
        showNavGuard.value = true;
        return;
    }
    navigateTo(index);
};

const navigateTo = (index) => {
    resetExerciseTimer();
    currentIndex.value = index;
    showNavGuard.value = false;
    pendingNavIndex.value = null;
    showDrawer.value = false;
    initSetsForExercise(sortedExercises.value[index]);
    autoStartNextSet(sortedExercises.value[index]);
};

const confirmNavigation = () => {
    if (pendingNavIndex.value !== null) {
        navigateTo(pendingNavIndex.value);
    }
};

// ─── Rest timer (full-screen overlay) ────────────────────────────────────────
// Baseado em delta contra um `targetEndTimestamp`: o tempo restante é sempre
// recalculado a partir de Date.now(), então volta correto instantaneamente
// ao ligar a tela. O alarme (<audio>, ver `Utils/restAlarm.js`) é disparado
// por dois caminhos que convergem em `finishRestNaturally()`: um setTimeout
// armado para o timestamp final (preciso enquanto a página está viva) e o
// `watch` do tempo restante (que cobre a volta de uma tela bloqueada, quando
// o navegador pode ter congelado o setTimeout).
const restActive = ref(false);
const restTotal = ref(60);
const restTargetEnd = ref(null);
let restEndHandled = false;

const restRemaining = computed(() => {
    if (!restActive.value || !restTargetEnd.value) return 0;
    return Math.max(0, Math.ceil((restTargetEnd.value - now.value) / 1000));
});

const restPercent = computed(() =>
    restTotal.value > 0 ? (restRemaining.value / restTotal.value) * 100 : 0,
);

const closeRest = () => {
    restActive.value = false;
    restTargetEnd.value = null;
    autoStartNextSet(currentExercise.value);
};

// Idempotente: o setTimeout e o watch podem chamar no mesmo fim de descanso.
const finishRestNaturally = () => {
    if (!restActive.value || restEndHandled) return;
    restEndHandled = true;
    disarmRestAlarm();
    playRestAlarm();
    // O overlay fecha, mas o alarme segue tocando até o fim do arquivo.
    setTimeout(() => {
        if (restActive.value) closeRest();
    }, 800);
};

const startRest = (seconds = 60) => {
    stopRestAlarm();
    restTotal.value = seconds;
    restTargetEnd.value = Date.now() + seconds * 1000;
    restActive.value = true;
    restEndHandled = false;
    armRestAlarm(restTargetEnd.value, finishRestNaturally);
};

const addRestTime = (secs) => {
    restTotal.value += secs;
    restTargetEnd.value += secs * 1000;
    armRestAlarm(restTargetEnd.value, finishRestNaturally);
};

// "Encerrar Descanso" (toque do usuário): encerra sem alarme.
const endRest = () => {
    disarmRestAlarm();
    stopRestAlarm();
    closeRest();
};

const restDisplay = computed(() => {
    const s = restRemaining.value;
    const m = Math.floor(s / 60);
    const sec = s % 60;
    return `${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
});

// Recalculado a cada tick do relógio compartilhado — inclusive no instante
// em que a tela volta a ficar visível: se o descanso acabou enquanto o
// celular estava bloqueado, o alarme toca na hora.
watch(restRemaining, (seconds) => {
    if (restActive.value && seconds <= 0) finishRestNaturally();
});

// ─── Finish workout ───────────────────────────────────────────────────────────
// Offline: a mutação vai para a fila e o app navega direto para o dashboard,
// exatamente como o redirect que o servidor faria após um `finish` online —
// o Service Worker serve essa navegação a partir do cache (ver
// `resources/pwa/sw.js`), então a transição é idêntica à do caminho online e
// a sincronização real acontece em segundo plano assim que a conexão voltar.
const finishWorkout = async () => {
    finishing.value = true;
    releaseWakeLock();

    // Encerra a sessão ativa localmente (IndexedDB) e expurga as páginas
    // Inertia cacheadas de imediato — antes mesmo da confirmação do
    // servidor ou da sincronização da fila — para que o banner global e a
    // Dashboard nunca continuem "achando" que este treino ainda está em
    // andamento (Phase 36).
    await finishActiveSessionLocally(props.workout?.id);

    if (props.offlineOnly || !isOnline.value) {
        await queueAction({
            type: 'finish_session',
            client_session_id: clientSessionId.value,
            session_id: props.offlineOnly ? null : props.session.id,
            occurred_at: new Date().toISOString(),
        });
        router.visit(route('dashboard'));
        return;
    }

    router.post(
        route('workout-sessions.finish', props.session.id),
        {},
        { onFinish: () => { finishing.value = false; } },
    );
};

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(() => {
    sortedExercises.value.forEach((ex) => initSetsForExercise(ex));
    autoStartNextSet(currentExercise.value);
    // Opt-in em Perfil → "Manter tela ligada durante o treino".
    if (getKeepScreenOn()) requestWakeLock();

    // Sessão reaberta (reload, banner, retomada offline): não houve o toque
    // em "Iniciar Treino" nesta página, então o primeiro toque em qualquer
    // lugar da tela é que libera o áudio do alarme.
    document.addEventListener('touchend', unlockRestAlarm, { capture: true, once: true });
    document.addEventListener('click', unlockRestAlarm, { capture: true, once: true });

    // Sessão sem WorkoutSession real no servidor ainda: não há o que
    // consultar para sugestões de sobrecarga. O card já sabe se degradar
    // graciosamente para o estado 'error' (mesmo texto neutro exibido quando
    // a chamada real falha por qualquer outro motivo).
    // Sem conexão também não: a chamada só produziria um 504 "Indisponível
    // offline" no console. O `watch(isOnline)` abaixo busca assim que a rede
    // voltar.
    if (props.offlineOnly || !isOnline.value) {
        overloadStatus.value = 'error';
    } else {
        fetchOverloadSuggestions();
    }

    // Cacheia treino e histórico recente no IndexedDB para uso offline
    // (permite reabrir/continuar esta ficha sem conexão em uma próxima vez).
    if (props.workout) {
        cacheWorkout({ ...props.workout, exercises: props.exercises });
    }
    cacheLastLogs(props.lastLogs);

    // Registra esta sessão como a sessão ativa local (IndexedDB) e avisa o
    // Service Worker para expurgar as páginas Inertia cacheadas — cobre
    // tanto o início de uma sessão nova quanto a reabertura/continuação de
    // uma já em andamento (Phase 36).
    // `client_session_id` e `offline_only` viajam junto: é com eles que o
    // indicador global sabe para onde apontar (a rota real da sessão, ou a
    // página da ficha quando a sessão ainda só existe neste aparelho) e que
    // o IndexedDB consegue reunir as séries desta sessão guardadas na fila.
    startActiveSessionLocally({
        id: props.session.id,
        client_session_id: clientSessionId.value,
        offline_only: props.offlineOnly,
        workout_id: props.workout?.id ?? null,
        workout_name: props.workout?.name ?? null,
        started_at: props.session.started_at,
    });
});

onUnmounted(() => {
    releaseWakeLock();
    document.removeEventListener('touchend', unlockRestAlarm, { capture: true });
    document.removeEventListener('click', unlockRestAlarm, { capture: true });
});

// A sessão abriu offline (sugestões puladas) e a rede voltou: busca agora.
watch(isOnline, (online) => {
    if (online && !props.offlineOnly && overloadStatus.value === 'error' && overloadSuggestions.value.length === 0) {
        fetchOverloadSuggestions();
    }
});

watch(
    () => props.setLogs,
    (newLogs) => {
        for (const log of newLogs) {
            const exId = log.exercise_id;
            if (!savedSets.value[exId]) savedSets.value[exId] = new Set();
            if (!setStates.value[exId]) setStates.value[exId] = {};
            savedSets.value[exId].add(log.set_number);
            setStates.value[exId][log.set_number] = 'done';

            // O servidor é a versão definitiva de cada série já persistida:
            // reconcilia a cópia local usada pelo volume total.
            upsertLocalSetLog(exId, log.set_number, { weight: log.weight, reps: log.reps });
        }
    },
    { deep: true },
);
</script>

<template>
    <Head :title="`🔥 ${workout.name}`" />

    <div class="fixed inset-0 flex flex-col bg-gray-950 text-white" style="z-index: 50">
        <SessionHeader
            :workout-name="workout.name"
            :elapsed-display="elapsedDisplay"
            :total-done-sets="totalDoneSets"
            :total-planned-sets="totalPlannedSets"
            :progress-percent="progressPercent"
            @open-drawer="showDrawer = true"
            @open-menu="sidebarOpen = true"
        />

        <ExerciseStepper
            :exercises="stepperExercises"
            :current-index="currentIndex"
            @navigate="tryNavigateTo"
        />

        <main class="flex-1 overflow-y-auto px-4 py-5 pb-32" v-if="currentExercise">
            <ExerciseActiveCard
                :exercise="currentExercise"
                :overload-suggestion="currentOverloadSuggestion"
                :overload-status="overloadStatus"
                :last-log="currentLastLog"
                :set-inputs="setInputs[currentExercise.id]"
                :sets="currentSets"
                :saving-set="savingSet"
                :exercise-timer-active="exerciseTimerActive"
                :exercise-timer-display="exerciseTimerDisplay"
                :is-exercise-done="isExerciseDone(currentExercise)"
                :is-last-exercise="isLastExercise"
                :can-add-extra-set="canAddExtraSet"
                @adjust-weight="(n, delta) => adjustWeight(currentExercise, n, delta)"
                @adjust-reps="(n, delta) => adjustReps(currentExercise, n, delta)"
                @set-weight="(n, value) => setWeight(currentExercise, n, value)"
                @set-reps="(n, value) => setReps(currentExercise, n, value)"
                @complete-set="(n) => completeSet(currentExercise, n)"
                @next-exercise="tryNavigateTo(currentIndex + 1)"
                @finish-workout="showFinishModal = true"
                @add-extra-set="addExtraSet(currentExercise)"
                @apply-overload-suggestion="applyOverloadSuggestion"
                @dismiss-overload-suggestion="dismissOverloadSuggestion"
            />
        </main>

        <!-- ─── Bottom Footer ────────────────────────────────────────────── -->
        <footer class="absolute bottom-0 left-0 right-0 border-t border-gray-800 bg-gray-900/95 backdrop-blur-sm px-4 py-3 z-40">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-xs text-gray-500">Volume total</p>
                    <p class="text-sm font-bold text-white">
                        {{ totalVolume.toLocaleString('pt-BR', { maximumFractionDigits: 1 }) }} kg
                    </p>
                </div>
                <button
                    @click="showFinishModal = true"
                    class="flex items-center gap-2 rounded-xl bg-gradient-to-r from-orange-500 to-red-500 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-orange-500/25 transition hover:from-orange-600 hover:to-red-600 active:scale-[0.97]"
                >
                    🔥 Finalizar
                </button>
            </div>
        </footer>
    </div>

    <RestTimerOverlay
        :active="restActive"
        :display="restDisplay"
        :percent="restPercent"
        :next-set-number="currentNextWaitingSet"
        :total-sets="currentTotalSets"
        @end-rest="endRest"
        @add-time="addRestTime"
    />

    <ExerciseOverviewDrawer
        :show="showDrawer"
        :exercises="drawerExercises"
        :current-index="currentIndex"
        @close="showDrawer = false"
        @select="navigateTo"
    />

    <NavigationGuardModal
        :show="showNavGuard"
        @continue="showNavGuard = false; pendingNavIndex = null"
        @confirm="confirmNavigation"
    />

    <FinishWorkoutModal
        :show="showFinishModal"
        :finishing="finishing"
        :elapsed-display="elapsedDisplay"
        :total-done-sets="totalDoneSets"
        :volume-display="volumeDisplay"
        @confirm="finishWorkout"
        @cancel="showFinishModal = false"
    />

    <Sidebar :open="sidebarOpen" @close="sidebarOpen = false" @logout="logout" />
</template>
