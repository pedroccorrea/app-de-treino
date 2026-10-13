<script setup>
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import Sidebar from '@/Components/Navigation/Sidebar.vue';
import EditSetModal from '@/Components/Sets/EditSetModal.vue';
import ExerciseActiveCard from '@/Components/WorkoutSessions/ExerciseActiveCard.vue';
import ExerciseOverviewDrawer from '@/Components/WorkoutSessions/ExerciseOverviewDrawer.vue';
import ExerciseStepper from '@/Components/WorkoutSessions/ExerciseStepper.vue';
import FinishWorkoutModal from '@/Components/WorkoutSessions/FinishWorkoutModal.vue';
import NavigationGuardModal from '@/Components/WorkoutSessions/NavigationGuardModal.vue';
import RestTimerOverlay from '@/Components/WorkoutSessions/RestTimerOverlay.vue';
import SessionHeader from '@/Components/WorkoutSessions/SessionHeader.vue';
import SupersetRoundCard from '@/Components/WorkoutSessions/SupersetRoundCard.vue';
import { useCompletedSetEdit } from '@/Composables/useCompletedSetEdit';
import { useNowTicker } from '@/Composables/useNowTicker';
import { useOfflineSync } from '@/Composables/useOfflineSync';
import { useRestAlarm } from '@/Composables/useRestAlarm';
import { useWakeLock } from '@/Composables/useWakeLock';
import { cacheLastLogs, cacheWorkout, generateUuid, recordActiveSessionSetLog } from '@/Utils/offlineStorage';
import { initialRepsFor, suggestedRepsFor } from '@/Utils/repTarget';
import { playRestAlarm, stopRestAlarm, unlockRestAlarm } from '@/Utils/restAlarm';
import {
    blockForIndex,
    buildSessionBlocks,
    currentRound,
    extraRoundExercises,
    pendingRoundExercises,
    roundExercises,
    roundRestSeconds,
    totalRounds,
} from '@/Utils/supersetRounds';
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

// ─── Blocos (exercício sozinho ou bi-set/tri-set) — Phase 53 ─────────────────
// Um grupo aparece como UM card por rodada (`SupersetRoundCard`); exercício
// sozinho continua no `ExerciseActiveCard` de sempre. `currentIndex` segue
// sendo o índice do exercício: o bloco em foco é o que o contém.
const sessionBlocks = computed(() => buildSessionBlocks(sortedExercises.value));
const currentBlock = computed(() => blockForIndex(sessionBlocks.value, currentIndex.value));
const currentGroup = computed(() => (currentBlock.value?.isGroup ? currentBlock.value : null));

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
        applySuggestedRepsToUntouchedSets();
    } catch (_) {
        overloadSuggestions.value = [];
        overloadStatus.value = 'error';
    }
};

const currentOverloadSuggestion = computed(() => {
    if (dismissedSuggestions.value[currentExercise.value?.id]) return null;
    return overloadSuggestions.value.find((s) => s.exercise_id === currentExercise.value?.id) ?? null;
});

const suggestionForExercise = (exerciseId) =>
    overloadSuggestions.value.find((s) => s.exercise_id === exerciseId) ?? null;

// Sem argumento (card de exercício sozinho) vale para o exercício em foco; no
// card da rodada, cada seção informa o seu exercício.
const dismissOverloadSuggestion = (exercise = currentExercise.value) => {
    if (exercise) dismissedSuggestions.value[exercise.id] = true;
};

// Applies a suggestion straight to the set currently "running" (if any).
const applyOverloadSuggestion = ({ load, reps }, exercise = currentExercise.value) => {
    if (!exercise) return;

    const runningSet = setsOf(exercise).find((s) => s.state === 'running');
    if (!runningSet) return;

    markSetTouched(exercise.id, runningSet.number);

    // A carga inicial pode vir sem número (ex: "Primeira vez"): o que vier
    // nulo mantém o valor que já estava na série.
    const current = setInputs.value[exercise.id][runningSet.number] ?? {};
    setInputs.value[exercise.id][runningSet.number] = {
        ...current,
        weight: load ?? current.weight,
        reps: reps ?? current.reps,
    };
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

// finishedEarly[exerciseId] = true quando o exercício foi encerrado pelo
// "Finalizar exercício" antes de todas as séries planejadas serem feitas.
const finishedEarly = ref({});

// pristineReps[exerciseId] = Set de séries cujo REPS ainda é só o valor
// inicial (ninguém tocou, nem veio de uma série concluída nesta sessão). Só
// essas recebem as repetições sugeridas pela IA quando a sugestão chega
// depois da tela abrir — o que a pessoa editou ou registrou nunca é
// sobrescrito.
const pristineReps = {};

const markSetPristine = (exId, n) => {
    if (!pristineReps[exId]) pristineReps[exId] = new Set();
    pristineReps[exId].add(n);
};

const markSetTouched = (exId, n) => {
    pristineReps[exId]?.delete(n);
};

const isSetPristine = (exId, n) => pristineReps[exId]?.has(n) ?? false;

const applySuggestedRepsToUntouchedSets = () => {
    for (const exercise of sortedExercises.value) {
        const reps = suggestedRepsFor(exercise, suggestionForExercise(exercise.id));
        if (reps === null) continue;

        for (const n of pristineReps[exercise.id] ?? []) {
            if (getSetState(exercise.id, n) === 'done' || !setInputs.value[exercise.id]?.[n]) continue;
            setInputs.value[exercise.id][n].reps = reps;
        }
    }
};

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

    // Série 1: herda a carga da última vez que o exercício foi feito. O REPS
    // é sempre um inteiro: sugestão da IA → última vez → menor número da
    // meta da ficha (nunca o texto "8-10") — ver `Utils/repTarget.js`.
    if (!setInputs.value[exId][1]) {
        setInputs.value[exId][1] = {
            weight: last?.weight ?? 0,
            reps: initialRepsFor(exercise, last, suggestionForExercise(exId)),
        };
        markSetPristine(exId, 1);
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
                reps: previous?.reps || initialRepsFor(exercise, last, suggestionForExercise(exId)),
            };
            // Herdou de uma série ainda intocada: continua sendo valor inicial.
            if (!previous || isSetPristine(exId, n - 1)) markSetPristine(exId, n);
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
    if (finishedEarly.value[exercise.id]) return true;
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

// ─── Rodadas do bi-set/tri-set (Phase 53) ────────────────────────────────────
// O estado do treino visto pelo utilitário puro `Utils/supersetRounds.js`.
// As funções leem os refs, então os `computed` que as usam reagem a eles.
const roundStatus = {
    totalSets: (exercise) => totalSetsForExercise(exercise),
    isFinished: (exercise) => !!finishedEarly.value[exercise.id],
    isSetDone: (exercise, n) => isSetSaved(exercise.id, n),
};

// Começa a rodada atual do grupo: a série N de cada exercício pendente fica
// "running" ao mesmo tempo e o cronômetro de série passa a valer para a
// rodada inteira (só para quando ela é concluída). Equivalente de
// `autoStartNextSet()` para grupos; no-op se o grupo terminou.
const autoStartRound = (block) => {
    const round = currentRound(block.exercises, roundStatus);
    if (round === null) return;

    for (const exercise of pendingRoundExercises(block.exercises, round, roundStatus)) {
        const states = setStates.value[exercise.id];
        for (const n in states) {
            if (states[n] === 'running' && Number(n) !== round) states[n] = 'waiting';
        }
        states[round] = 'running';
    }

    if (!exerciseTimerActive.value) startExerciseTimer();
};

// Começa o que estiver pendente no bloco do exercício da posição `index`.
const autoStartAt = (index) => {
    const block = blockForIndex(sessionBlocks.value, index);
    if (block?.isGroup) {
        autoStartRound(block);
    } else {
        autoStartNextSet(sortedExercises.value[index]);
    }
};

const currentGroupRound = computed(() =>
    currentGroup.value ? currentRound(currentGroup.value.exercises, roundStatus) : null,
);

const currentGroupTotalRounds = computed(() =>
    currentGroup.value ? totalRounds(currentGroup.value.exercises, roundStatus) : 0,
);

// Exercícios exibidos no card: os da rodada atual, ou todos quando o grupo
// terminou (só para consultar/editar as séries feitas).
const currentRoundExercises = computed(() => {
    if (!currentGroup.value) return [];
    if (currentGroupRound.value === null) return currentGroup.value.exercises;
    return roundExercises(currentGroup.value.exercises, currentGroupRound.value, roundStatus);
});

const currentRoundRestSeconds = computed(() => roundRestSeconds(currentRoundExercises.value));

const doneSetsOf = (exercise) =>
    [...(savedSets.value[exercise.id] ?? [])]
        .sort((a, b) => a - b)
        .map((number) => ({ number, ...(setInputs.value[exercise.id]?.[number] ?? {}) }));

const roundSections = computed(() => {
    const round = currentGroupRound.value;
    return currentRoundExercises.value.map((exercise) => ({
        exercise,
        setNumber: round,
        totalSets: totalSetsForExercise(exercise),
        inputs: round === null ? null : (setInputs.value[exercise.id]?.[round] ?? null),
        roundSetDone: round !== null && isSetSaved(exercise.id, round),
        doneSets: doneSetsOf(exercise),
        lastLog: props.lastLogs?.[exercise.id] ?? null,
        overloadSuggestion: dismissedSuggestions.value[exercise.id] ? null : suggestionForExercise(exercise.id),
    }));
});

// Último exercício do bloco em foco é o último da ficha?
const isLastBlock = computed(() =>
    currentBlock.value ? currentBlock.value.indexes.at(-1) === totalExercises.value - 1 : true,
);

// "Próximo Exercício"/"Pular" a partir do grupo: o primeiro depois do bloco.
const indexAfterCurrentBlock = computed(() =>
    currentBlock.value ? currentBlock.value.indexes.at(-1) + 1 : currentIndex.value + 1,
);

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

// [{ number, state }] de todas as séries (planejadas + extras) do exercício.
const setsOf = (exercise) =>
    Array.from({ length: totalSetsForExercise(exercise) }, (_, i) => {
        const number = i + 1;
        return { number, state: getSetState(exercise.id, number) };
    });

const currentSets = computed(() => (currentExercise.value ? setsOf(currentExercise.value) : []));

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

// Marca a série como concluída na UI local (sem descanso) — base de
// `markSetCompleteLocally()` e de cada série do "Concluir rodada".
const recordSetLocally = (exercise, setNumber, inputs) => {
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
        // Herda do que foi registrado agora: a sugestão que chegar depois
        // não sobrescreve mais esta série.
        markSetTouched(exId, nextSetNumber);
        setInputs.value[exId][nextSetNumber] = {
            weight: inputs.weight !== '' && inputs.weight !== null ? inputs.weight : 0,
            reps: inputs.reps,
        };
    }
};

// Marca a série como concluída na UI local e agenda o descanso — usado tanto
// pelo caminho online (após confirmação do servidor) quanto pelo offline
// (otimista, já que a mutação só será confirmada quando a fila sincronizar).
const markSetCompleteLocally = (exercise, setNumber, inputs) => {
    recordSetLocally(exercise, setNumber, inputs);
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

// ─── Complete a round of a bi-set/tri-set (Phase 53) ─────────────────────────
// Um toque registra a série N de cada exercício pendente da rodada, cada uma
// como uma série NORMAL pelo mesmo caminho de `completeSet()`: rota online
// ou `log_set` na fila offline (cada uma com o seu `client_set_id`). O
// backend faz `updateOrCreate` por exercício + série, então nada duplica.
//
// Online, as séries vão UMA DE CADA VEZ (uma visita do Inertia cancelaria a
// anterior). Se alguma falhar, a rodada para ali: as já salvas ficam
// concluídas, a que falhou não, o descanso não começa e o próximo toque
// envia só o que faltou. O descanso só começa — uma única vez — depois de
// todas registradas, com o tempo do último exercício da rodada
// (`roundRestSeconds`).
const savingRound = ref(false);
const roundError = ref(null);

// Resolve `true` só quando o servidor confirmou a série.
const postSetOnline = (payload) => new Promise((resolve) => {
    let saved = false;
    router.post(route('workout-sessions.sets.store', props.session.id), payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { saved = true; },
        onCancel: () => resolve(false),
        onFinish: () => resolve(saved),
    });
});

const completeRound = async (block) => {
    // Mesmo desbloqueio de áudio do "Concluir série", ainda dentro do toque.
    unlockRestAlarm();
    if (savingRound.value) return;

    const round = currentRound(block.exercises, roundStatus);
    if (round === null) return;

    const inRound = roundExercises(block.exercises, round, roundStatus);
    savingRound.value = true;
    roundError.value = null;

    try {
        for (const exercise of inRound.filter((ex) => !isSetSaved(ex.id, round))) {
            const inputs = setInputs.value[exercise.id]?.[round] ?? {};
            const payload = {
                exercise_id: exercise.id,
                set_number: round,
                weight: inputs.weight !== '' ? inputs.weight : null,
                reps: inputs.reps,
            };

            if (props.offlineOnly || !isOnline.value) {
                await queueAction({
                    type: 'log_set',
                    client_session_id: clientSessionId.value,
                    client_set_id: generateUuid(),
                    session_id: props.offlineOnly ? null : props.session.id,
                    occurred_at: new Date().toISOString(),
                    ...payload,
                });
            } else if (!(await postSetOnline(payload))) {
                roundError.value = `Não foi possível salvar ${exercise.name}. Toque em "Concluir rodada" para tentar de novo.`;
                return;
            }

            recordSetLocally(exercise, round, inputs);
        }

        stopExerciseTimer();
        startRest(roundRestSeconds(inRound));
    } catch (_) {
        roundError.value = 'Não foi possível salvar a rodada. Toque em "Concluir rodada" para tentar de novo.';
    } finally {
        notifySessionChanged();
        savingRound.value = false;
    }
};

// ─── Edit a completed set (Phase 46) ─────────────────────────────────────────
// Tocar no card de uma série concluída abre o mesmo editor do histórico
// (`Sets/EditSetModal.vue` → `Sets/SetEditor.vue`). Salvar reenvia a série
// pelo mesmo caminho de `completeSet()` (rota online ou `log_set` na fila),
// que o backend aplica como `updateOrCreate` — corrige, nunca duplica.
// Propositalmente NÃO passa por `markSetCompleteLocally()`: editar uma série
// anterior não mexe em estados, cronômetro, descanso, alarme nem na
// pré-carga da próxima série.
const applyEditedSet = (exerciseId, setNumber, { weight, reps }) => {
    setInputs.value[exerciseId][setNumber] = { weight: weight ?? '', reps };
    upsertLocalSetLog(exerciseId, setNumber, { weight, reps });
    recordActiveSessionSetLog({ exercise_id: exerciseId, set_number: setNumber, weight, reps });
    notifySessionChanged();
};

const {
    editing: editingSet,
    showEdit: showSetEdit,
    form: setEditForm,
    queueing: setEditQueueing,
    openEdit,
    closeEdit: closeSetEdit,
    saveEdit: saveSetEdit,
} = useCompletedSetEdit({
    sessionId: () => props.session.id,
    clientSessionId: () => clientSessionId.value,
    offlineOnly: () => props.offlineOnly,
    isOnline,
    queueAction,
    onSaved: applyEditedSet,
});

// Abre o editor com os valores salvos da série (a cópia local é o que conta
// no volume). Sem carga vira `null`, que o editor mostra como "—kg".
const openSetEdit = (exercise, setNumber) => {
    if (getSetState(exercise.id, setNumber) !== 'done') return;

    const saved = localSetLogs.value.find(
        (log) => log.exercise_id === exercise.id && log.set_number === setNumber,
    ) ?? setInputs.value[exercise.id]?.[setNumber] ?? {};

    const weight = saved.weight === '' || saved.weight === null || saved.weight === undefined
        ? null
        : parseFloat(saved.weight);

    openEdit(exercise, {
        set_number: setNumber,
        weight: Number.isNaN(weight) ? null : weight,
        reps: Math.max(1, parseInt(saved.reps) || 1),
    });
};

// ─── Quick weight/reps adjustments ───────────────────────────────────────────
const adjustWeight = (exercise, setNumber, delta) => {
    const exId = exercise.id;
    markSetTouched(exId, setNumber);
    const current = parseFloat(setInputs.value[exId]?.[setNumber]?.weight) || 0;
    // Arredonda em centésimos: o delta vem do passo do equipamento
    // (loadSteps.js) e somas de float não podem gerar 11,999999 kg.
    setInputs.value[exId][setNumber].weight = Math.max(0, Math.round((current + delta) * 100) / 100);
};

const adjustReps = (exercise, setNumber, delta) => {
    const exId = exercise.id;
    markSetTouched(exId, setNumber);
    const current = parseInt(setInputs.value[exId]?.[setNumber]?.reps) || 0;
    setInputs.value[exId][setNumber].reps = Math.max(1, current + delta);
};

// ─── Direct weight/reps entry (tap-to-edit on the load/reps hero) ───────────
const setWeight = (exercise, setNumber, value) => {
    const exId = exercise.id;
    markSetTouched(exId, setNumber);
    setInputs.value[exId][setNumber].weight = Math.max(0, value);
};

const setReps = (exercise, setNumber, value) => {
    const exId = exercise.id;
    markSetTouched(exId, setNumber);
    setInputs.value[exId][setNumber].reps = Math.max(1, value);
};

// ─── Add extra set ────────────────────────────────────────────────────────────
// Cria a nova série (sem iniciá-la) — base do "+ série extra" e do
// "+ rodada extra" do grupo.
const appendExtraSet = (exercise) => {
    const exId = exercise.id;
    // Uma série extra depois de "Finalizar exercício" reabre o exercício:
    // ele volta a exigir a conclusão dessa nova série.
    delete finishedEarly.value[exId];
    extraSets.value[exId] = (extraSets.value[exId] ?? 0) + 1;
    const newSetNum = totalSetsForExercise(exercise);
    const previous = setInputs.value[exId]?.[newSetNum - 1];
    const last = props.lastLogs?.[exId];
    setInputs.value[exId][newSetNum] = {
        weight: previous?.weight ?? last?.weight ?? 0,
        reps: previous?.reps || initialRepsFor(exercise, last, suggestionForExercise(exId)),
    };
    markSetTouched(exId, newSetNum);
    setStates.value[exId][newSetNum] = 'waiting';
};

const addExtraSet = (exercise) => {
    appendExtraSet(exercise);
    autoStartNextSet(exercise);
};

// "+ Adicionar rodada extra" (grupo terminado): uma série a mais para quem
// chegou à última rodada, formando exatamente a rodada seguinte.
const addExtraRound = (block) => {
    extraRoundExercises(block.exercises, roundStatus).forEach(appendExtraSet);
    autoStartRound(block);
};

// ─── Finish exercise early ("Finalizar exercício") ───────────────────────────
// Encerra o exercício com as séries feitas até aqui. É uma decisão só de
// interface: nada é enviado ao servidor nem à fila offline — as séries
// concluídas já foram persistidas uma a uma por `completeSet()`, e as não
// feitas simplesmente nunca viram SetLog (logo ficam fora de volume,
// histórico, estatísticas e da análise de progressão da IA).
const showFinishExerciseModal = ref(false);

// Exercício que o modal vai encerrar: o em foco, ou — no card da rodada — o
// da seção onde o botão foi tocado.
const finishExerciseTarget = ref(null);

const openFinishExercise = (exercise) => {
    finishExerciseTarget.value = exercise;
    showFinishExerciseModal.value = true;
};

const targetDoneSetsCount = computed(() =>
    finishExerciseTarget.value ? (savedSets.value[finishExerciseTarget.value.id]?.size ?? 0) : 0,
);

const groupBlockOf = (exercise) => {
    const block = exercise ? blockForIndex(sessionBlocks.value, sortedExercises.value.indexOf(exercise)) : null;
    return block?.isGroup ? block : null;
};

// No grupo o título diz QUAL exercício será encerrado (o card tem vários).
const finishExerciseModalTitle = computed(() => {
    const target = finishExerciseTarget.value;
    const name = groupBlockOf(target) ? target.name : 'exercício';
    return `Finalizar ${name} com ${targetDoneSetsCount.value} de ${target ? totalSetsForExercise(target) : 0} séries feitas?`;
});

const finishExerciseEarly = (exercise) => {
    const exId = exercise.id;
    showFinishExerciseModal.value = false;

    // Dentro de um grupo, encerra só este exercício: o cronômetro é da
    // rodada e continua valendo para os outros.
    const block = groupBlockOf(exercise);
    const inGroup = block !== null;

    // Descarta a série em execução: volta a "waiting" e o cronômetro zera.
    for (const n in setStates.value[exId] ?? {}) {
        if (setStates.value[exId][n] === 'running') setStates.value[exId][n] = 'waiting';
    }
    if (!inGroup) resetExerciseTimer();

    // O total planejado encolhe até a última série feita — assim o
    // progresso do cabeçalho, o drawer e o stepper contam só o que foi feito.
    const doneNumbers = [...(savedSets.value[exId] ?? [])];
    const lastDone = doneNumbers.length ? Math.max(...doneNumbers) : 0;
    extraSets.value[exId] = lastDone - (exercise.target_sets ?? 3);
    finishedEarly.value[exId] = true;

    // A partir daqui `isExerciseDone()` é true: o card cai no mesmo estado
    // de "Exercício concluído" que a última série concluída produz, com o
    // "Próximo Exercício" ou o "Finalizar Treino" (se for o último).

    // No grupo, as próximas rodadas seguem só com os outros. Se este era o
    // único pendente da rodada, a próxima começa já (sem descanso: nada foi
    // feito agora); se todos terminaram, o grupo termina e o cronômetro zera.
    if (inGroup) {
        if (currentRound(block.exercises, roundStatus) === null) {
            resetExerciseTimer();
        } else {
            autoStartRound(block);
        }
    }
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
    const block = blockForIndex(sessionBlocks.value, index);
    (block?.isGroup ? block.exercises : [sortedExercises.value[index]]).forEach(initSetsForExercise);
    autoStartAt(index);
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
    // Grupo: a próxima rodada começa sozinha, como a próxima série.
    autoStartAt(currentIndex.value);
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
    autoStartAt(currentIndex.value);
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
            <!-- Bi-set/tri-set: um card por rodada (Phase 53). -->
            <SupersetRoundCard
                v-if="currentGroup"
                :label="currentGroup.label"
                :round="currentGroupRound"
                :total-rounds="currentGroupTotalRounds"
                :sections="roundSections"
                :overload-status="overloadStatus"
                :saving="savingRound"
                :error="roundError"
                :exercise-timer-active="exerciseTimerActive"
                :exercise-timer-display="exerciseTimerDisplay"
                :rest-seconds="currentRoundRestSeconds"
                :is-last-block="isLastBlock"
                @adjust-weight="adjustWeight"
                @adjust-reps="adjustReps"
                @set-weight="setWeight"
                @set-reps="setReps"
                @edit-set="openSetEdit"
                @finish-exercise="openFinishExercise"
                @apply-overload-suggestion="(exercise, payload) => applyOverloadSuggestion(payload, exercise)"
                @dismiss-overload-suggestion="dismissOverloadSuggestion"
                @complete-round="completeRound(currentGroup)"
                @next-exercise="tryNavigateTo(indexAfterCurrentBlock)"
                @finish-workout="showFinishModal = true"
                @add-extra-round="addExtraRound(currentGroup)"
            />
            <ExerciseActiveCard
                v-else
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
                :is-finished-early="!!finishedEarly[currentExercise.id]"
                @adjust-weight="(n, delta) => adjustWeight(currentExercise, n, delta)"
                @adjust-reps="(n, delta) => adjustReps(currentExercise, n, delta)"
                @set-weight="(n, value) => setWeight(currentExercise, n, value)"
                @set-reps="(n, value) => setReps(currentExercise, n, value)"
                @complete-set="(n) => completeSet(currentExercise, n)"
                @edit-set="(n) => openSetEdit(currentExercise, n)"
                @next-exercise="tryNavigateTo(currentIndex + 1)"
                @finish-workout="showFinishModal = true"
                @add-extra-set="addExtraSet(currentExercise)"
                @finish-exercise="openFinishExercise(currentExercise)"
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
        :next-set-number="currentGroup ? null : currentNextWaitingSet"
        :total-sets="currentTotalSets"
        :next-round-number="currentGroupRound"
        :total-rounds="currentGroupTotalRounds"
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

    <ConfirmationModal
        :show="showFinishExerciseModal"
        :title="finishExerciseModalTitle"
        description="As séries não feitas não serão registradas."
        confirm-text="Finalizar"
        cancel-text="Cancelar"
        :danger="false"
        @confirm="finishExerciseTarget && finishExerciseEarly(finishExerciseTarget)"
        @cancel="showFinishExerciseModal = false"
    />

    <EditSetModal
        :show="showSetEdit"
        :set="editingSet?.set ?? null"
        :exercise-name="editingSet?.exerciseName ?? ''"
        :equipment="editingSet?.equipment ?? null"
        :processing="setEditForm.processing || setEditQueueing"
        :errors="setEditForm.errors"
        @save="saveSetEdit"
        @close="closeSetEdit"
    />

    <Sidebar :open="sidebarOpen" @close="sidebarOpen = false" @logout="logout" />
</template>
