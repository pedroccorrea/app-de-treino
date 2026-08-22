<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps({
    session: { type: Object, required: true },
    workout: { type: Object, required: true },
    exercises: { type: Array, required: true },
    setLogs: { type: Array, default: () => [] },
    lastLogs: { type: Object, default: () => ({}) },
});

// ─── Sorted exercises ─────────────────────────────────────────────────────────
const sortedExercises = computed(() =>
    [...props.exercises].sort((a, b) => (a.order ?? 0) - (b.order ?? 0)),
);

// ─── Focus state ──────────────────────────────────────────────────────────────
const currentIndex = ref(0);
const currentExercise = computed(() => sortedExercises.value[currentIndex.value] ?? null);
const totalExercises = computed(() => sortedExercises.value.length);

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
const exerciseTimerActive = ref(false);
const exerciseTimerSeconds = ref(0);
let exerciseTimerInterval = null;

const startExerciseTimer = () => {
    exerciseTimerActive.value = true;
    exerciseTimerSeconds.value = 0;
    clearInterval(exerciseTimerInterval);
    exerciseTimerInterval = setInterval(() => {
        exerciseTimerSeconds.value++;
    }, 1000);
};

const stopExerciseTimer = () => {
    exerciseTimerActive.value = false;
    clearInterval(exerciseTimerInterval);
};

const resetExerciseTimer = () => {
    stopExerciseTimer();
    exerciseTimerSeconds.value = 0;
};

const exerciseTimerDisplay = computed(() => {
    const s = exerciseTimerSeconds.value;
    const m = Math.floor(s / 60);
    const sec = s % 60;
    return `${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
});

// ─── Workout elapsed timer ────────────────────────────────────────────────────
const elapsedSeconds = ref(0);
let workoutTimerInterval = null;

const calculateInitialElapsed = () => {
    if (!props.session.started_at) return 0;
    return Math.floor((Date.now() - new Date(props.session.started_at).getTime()) / 1000);
};

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

    // Init remaining sets
    for (let n = 1; n <= targetSets; n++) {
        if (!setInputs.value[exId][n]) {
            setInputs.value[exId][n] = {
                weight: last?.weight ?? '',
                reps: last?.reps ?? '',
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
const totalVolume = computed(() =>
    props.setLogs.reduce((sum, log) => {
        if (log.weight && log.reps) return sum + parseFloat(log.weight) * parseInt(log.reps);
        return sum;
    }, 0),
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

const completeSet = (exercise, setNumber) => {
    const exId = exercise.id;
    const inputs = setInputs.value[exId]?.[setNumber] ?? {};
    savingSet.value = `${exId}-${setNumber}`;

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
                if (!savedSets.value[exId]) savedSets.value[exId] = new Set();
                savedSets.value[exId].add(setNumber);
                setStates.value[exId][setNumber] = 'done';
                savingSet.value = null;

                // Stop exercise timer and start rest
                stopExerciseTimer();
                exerciseTimerSeconds.value = 0;
                const restSecs = exercise.rest_seconds ?? 60;
                startRest(restSecs);
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

// ─── Add extra set ────────────────────────────────────────────────────────────
const addExtraSet = (exercise) => {
    const exId = exercise.id;
    extraSets.value[exId] = (extraSets.value[exId] ?? 0) + 1;
    const newSetNum = totalSetsForExercise(exercise);
    const last = props.lastLogs?.[exId];
    setInputs.value[exId][newSetNum] = { weight: last?.weight ?? '', reps: last?.reps ?? '' };
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
const restActive = ref(false);
const restTotal = ref(60);
const restRemaining = ref(60);
let restInterval = null;

const restPercent = computed(() =>
    restTotal.value > 0 ? (restRemaining.value / restTotal.value) * 100 : 0,
);

const startRest = (seconds = 60) => {
    clearInterval(restInterval);
    restTotal.value = seconds;
    restRemaining.value = seconds;
    restActive.value = true;

    restInterval = setInterval(() => {
        restRemaining.value--;
        if (restRemaining.value <= 0) {
            clearInterval(restInterval);
            playChime();
            if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
            // Auto-close after chime, same as clicking "Encerrar Descanso"
            setTimeout(() => { endRest(); }, 800);
        }
    }, 1000);
};

const addRestTime = (secs) => {
    restRemaining.value += secs;
    restTotal.value += secs;
};

const endRest = () => {
    clearInterval(restInterval);
    restActive.value = false;
    autoStartNextSet(currentExercise.value);
};

const restDisplay = computed(() => {
    const s = restRemaining.value;
    const m = Math.floor(s / 60);
    const sec = s % 60;
    return `${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
});

const playChime = () => {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        [523, 659, 784].forEach((freq, i) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = freq;
            osc.type = 'sine';
            gain.gain.setValueAtTime(0.3, ctx.currentTime + i * 0.15);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + i * 0.15 + 0.4);
            osc.start(ctx.currentTime + i * 0.15);
            osc.stop(ctx.currentTime + i * 0.15 + 0.5);
        });
    } catch (_) {}
};

// ─── Finish workout ───────────────────────────────────────────────────────────
const finishWorkout = () => {
    finishing.value = true;
    router.post(
        route('workout-sessions.finish', props.session.id),
        {},
        { onFinish: () => { finishing.value = false; } },
    );
};

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(() => {
    elapsedSeconds.value = calculateInitialElapsed();
    workoutTimerInterval = setInterval(() => { elapsedSeconds.value++; }, 1000);
    sortedExercises.value.forEach((ex) => initSetsForExercise(ex));
    autoStartNextSet(currentExercise.value);
});

onUnmounted(() => {
    clearInterval(workoutTimerInterval);
    clearInterval(exerciseTimerInterval);
    clearInterval(restInterval);
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
        }
    },
    { deep: true },
);
</script>

<template>
    <Head :title="`🔥 ${workout.name}`" />

    <div class="fixed inset-0 flex flex-col bg-gray-950 text-white" style="z-index: 50">

        <!-- ─── Top Header ──────────────────────────────────────────────── -->
        <header class="flex-shrink-0 border-b border-gray-800 bg-gray-900/80 backdrop-blur-sm px-4 py-3">
            <div class="flex items-center justify-between gap-3">
                <!-- Left: workout name + elapsed + exercise timer -->
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="relative flex h-2 w-2 flex-shrink-0">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-violet-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-violet-500"></span>
                        </span>
                        <p class="truncate text-sm font-bold text-white">{{ workout.name }}</p>
                    </div>
                    <!-- Dual timers: workout elapsed + current set timer -->
                    <div class="mt-0.5 flex items-center gap-3">
                        <p class="text-xs font-mono text-violet-400">⏱ {{ elapsedDisplay }}</p>
                        <Transition name="fade">
                            <p
                                v-if="exerciseTimerActive"
                                class="text-xs font-mono font-bold text-orange-400"
                            >
                                🏋️ {{ exerciseTimerDisplay }}
                            </p>
                        </Transition>
                    </div>
                </div>

                <!-- Right: overview + progress -->
                <div class="flex items-center gap-3">
                    <button
                        @click="showDrawer = true"
                        class="flex items-center gap-1.5 rounded-lg bg-gray-800 px-3 py-1.5 text-xs font-semibold text-gray-300 transition hover:bg-gray-700 hover:text-white"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        Lista
                    </button>
                    <div class="text-right">
                        <p class="text-xs text-gray-400">{{ totalDoneSets }}/{{ totalPlannedSets }} séries</p>
                        <div class="mt-1 h-1.5 w-20 overflow-hidden rounded-full bg-gray-700">
                            <div
                                class="h-full rounded-full bg-gradient-to-r from-violet-500 to-emerald-400 transition-all duration-500"
                                :style="{ width: progressPercent + '%' }"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- ─── Stepper ─────────────────────────────────────────────────── -->
        <div class="flex-shrink-0 border-b border-gray-800 bg-gray-900/60 px-4 py-3">
            <div class="flex items-center justify-between gap-2">
                <button
                    @click="tryNavigateTo(currentIndex - 1)"
                    :disabled="currentIndex === 0"
                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-gray-800 text-gray-400 transition hover:bg-gray-700 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>

                <div class="flex flex-1 items-center justify-center gap-1.5 overflow-x-auto px-1">
                    <button
                        v-for="(ex, idx) in sortedExercises"
                        :key="ex.id"
                        @click="tryNavigateTo(idx)"
                        :class="[
                            'h-2.5 rounded-full transition-all duration-300 flex-shrink-0',
                            idx === currentIndex
                                ? 'w-6 bg-violet-500'
                                : isExerciseDone(ex)
                                  ? 'w-2.5 bg-emerald-400'
                                  : 'w-2.5 bg-gray-600 hover:bg-gray-500',
                        ]"
                        :title="ex.name"
                    />
                </div>

                <button
                    @click="tryNavigateTo(currentIndex + 1)"
                    :disabled="currentIndex === totalExercises - 1"
                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-gray-800 text-gray-400 transition hover:bg-gray-700 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>

            <p class="mt-2 text-center text-sm font-semibold text-white truncate px-10">
                {{ currentExercise?.name }}
            </p>
            <p class="mt-0.5 text-center text-xs text-gray-500">
                Exercício {{ currentIndex + 1 }} de {{ totalExercises }}
            </p>
        </div>

        <!-- ─── Main Focus Area ──────────────────────────────────────────── -->
        <main class="flex-1 overflow-y-auto px-4 py-5 pb-32" v-if="currentExercise">

            <!-- Exercise meta card -->
            <div class="rounded-2xl bg-gray-900 border border-gray-800 p-4 mb-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <h2 class="text-xl font-bold text-white truncate">{{ currentExercise.name }}</h2>
                        <p class="mt-0.5 text-sm text-gray-400">{{ currentExercise.primary_muscle }}</p>
                    </div>
                </div>

                <!-- Reference from last workout -->
                <div
                    v-if="lastLogs?.[currentExercise.id]"
                    class="mt-3 flex items-center gap-2 rounded-xl bg-gray-800/70 px-3 py-2"
                >
                    <span class="text-base">📊</span>
                    <div>
                        <p class="text-xs text-gray-500">Último treino</p>
                        <p class="text-sm font-bold text-emerald-400">
                            {{ lastLogs[currentExercise.id].summary }}
                        </p>
                    </div>
                </div>

                <!-- Target -->
                <div v-if="currentExercise.target_sets" class="mt-2">
                    <p class="text-xs text-gray-500">
                        Meta:
                        <span class="font-semibold text-violet-400">
                            {{ currentExercise.target_sets }} séries
                            <template v-if="currentExercise.target_reps">
                                × {{ currentExercise.target_reps }} reps
                            </template>
                        </span>
                    </p>
                </div>
            </div>

            <!-- ─── Sets ─────────────────────────────────────────────── -->
            <div class="space-y-3">
                <template v-for="n in totalSetsForExercise(currentExercise)" :key="n">

                    <!-- ── DONE: Saved set (compact green row) ── -->
                    <div
                        v-if="getSetState(currentExercise.id, n) === 'done'"
                        class="flex items-center gap-3 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3"
                    >
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-500 text-sm font-bold text-white flex-shrink-0">
                            ✓
                        </span>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-emerald-400">Série {{ n }}</p>
                            <p class="text-xs text-gray-500">
                                {{ setInputs[currentExercise.id]?.[n]?.weight || '—' }}kg
                                × {{ setInputs[currentExercise.id]?.[n]?.reps || '—' }} reps
                            </p>
                        </div>
                        <svg class="h-5 w-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>

                    <!-- ── RUNNING: Active set with inputs ── -->
                    <div
                        v-else-if="getSetState(currentExercise.id, n) === 'running'"
                        class="rounded-2xl border border-violet-500/60 bg-gray-900 p-4 ring-1 ring-violet-500/20"
                    >
                        <!-- Header -->
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-violet-600 text-sm font-bold text-white">
                                    {{ n }}
                                </span>
                                <p class="text-sm font-semibold text-violet-300">
                                    Série {{ n }}/{{ totalSetsForExercise(currentExercise) }} — Executando
                                </p>
                            </div>
                            <!-- Live set timer badge -->
                            <Transition name="fade">
                                <span
                                    v-if="exerciseTimerActive"
                                    class="font-mono text-sm font-bold text-orange-400"
                                >
                                    {{ exerciseTimerDisplay }}
                                </span>
                            </Transition>
                        </div>

                        <!-- Weight input -->
                        <div class="mb-3">
                            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Peso (kg)
                            </label>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="adjustWeight(currentExercise, n, -2.5)"
                                    class="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-800 text-xl font-bold text-gray-300 transition hover:bg-gray-700 hover:text-white active:scale-95"
                                >−</button>
                                <input
                                    v-model="setInputs[currentExercise.id][n].weight"
                                    type="number"
                                    min="0"
                                    step="0.5"
                                    placeholder="0"
                                    class="flex-1 rounded-xl border-gray-700 bg-gray-800 py-3 text-center text-2xl font-bold text-white focus:border-violet-500 focus:ring-violet-500"
                                />
                                <button
                                    type="button"
                                    @click="adjustWeight(currentExercise, n, 2.5)"
                                    class="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-800 text-xl font-bold text-gray-300 transition hover:bg-gray-700 hover:text-white active:scale-95"
                                >+</button>
                            </div>
                        </div>

                        <!-- Reps input -->
                        <div class="mb-4">
                            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Repetições
                            </label>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="adjustReps(currentExercise, n, -1)"
                                    class="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-800 text-xl font-bold text-gray-300 transition hover:bg-gray-700 hover:text-white active:scale-95"
                                >−</button>
                                <input
                                    v-model="setInputs[currentExercise.id][n].reps"
                                    type="number"
                                    min="1"
                                    placeholder="0"
                                    class="flex-1 rounded-xl border-gray-700 bg-gray-800 py-3 text-center text-2xl font-bold text-white focus:border-violet-500 focus:ring-violet-500"
                                />
                                <button
                                    type="button"
                                    @click="adjustReps(currentExercise, n, 1)"
                                    class="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-800 text-xl font-bold text-gray-300 transition hover:bg-gray-700 hover:text-white active:scale-95"
                                >+</button>
                            </div>
                        </div>

                        <!-- Complete set button -->
                        <button
                            type="button"
                            @click="completeSet(currentExercise, n)"
                            :disabled="savingSet === `${currentExercise.id}-${n}`"
                            class="flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-violet-600 to-violet-500 py-4 text-base font-bold text-white shadow-lg shadow-violet-500/30 transition hover:from-violet-700 hover:to-violet-600 active:scale-[0.98] disabled:opacity-60"
                        >
                            <span v-if="savingSet === `${currentExercise.id}-${n}`" class="flex items-center gap-2">
                                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                Salvando...
                            </span>
                            <span v-else>✓ Concluir Série {{ n }}</span>
                        </button>
                    </div>

                    <!-- Sets in "waiting" state are intentionally not rendered: the next
                         set only becomes visible (and running) after rest ends. -->

                </template>
            </div>

            <!-- Exercise done feedback + actions -->
            <div v-if="isExerciseDone(currentExercise)" class="mt-4 space-y-3">
                <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-center">
                    <p class="text-2xl">🎉</p>
                    <p class="mt-1 font-bold text-emerald-400">Exercício concluído!</p>
                    <p class="text-xs text-gray-500 mt-0.5">Todas as séries foram registradas.</p>
                </div>

                <button
                    v-if="currentIndex < totalExercises - 1"
                    @click="tryNavigateTo(currentIndex + 1)"
                    class="flex w-full items-center justify-center gap-2 rounded-2xl bg-gray-800 py-3.5 text-sm font-bold text-white transition hover:bg-gray-700"
                >
                    Próximo Exercício →
                </button>
                <button
                    v-else
                    @click="showFinishModal = true"
                    class="flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-orange-500 to-red-500 py-3.5 text-sm font-bold text-white shadow-lg shadow-orange-500/25 transition hover:from-orange-600 hover:to-red-600"
                >
                    🔥 Finalizar Treino
                </button>

                <button
                    @click="addExtraSet(currentExercise)"
                    class="flex w-full items-center justify-center gap-2 rounded-2xl border border-dashed border-gray-700 py-2.5 text-sm text-gray-400 transition hover:border-violet-500/50 hover:text-violet-400"
                >
                    + Adicionar série extra
                </button>
            </div>

            <button
                v-else-if="totalSetsForExercise(currentExercise) < 10 && !nextWaitingSet(currentExercise)"
                @click="addExtraSet(currentExercise)"
                class="mt-4 flex w-full items-center justify-center gap-2 rounded-2xl border border-dashed border-gray-700 py-2.5 text-sm text-gray-400 transition hover:border-violet-500/50 hover:text-violet-400"
            >
                + Adicionar série extra
            </button>
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

    <!-- ─── Rest Timer Overlay (Película Fullscreen) ─────────────────────── -->
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="restActive"
                class="fixed inset-0 z-[60] flex flex-col items-center justify-center bg-black/85 backdrop-blur-sm px-6"
                style="overflow: hidden;"
            >
                <div class="w-full max-w-sm">
                    <!-- Label -->
                    <p class="mb-2 text-center text-xs font-semibold uppercase tracking-widest text-violet-400">
                        ⏸ Descansando
                    </p>

                    <!-- Big countdown -->
                    <div class="text-center">
                        <p class="font-mono text-8xl font-bold text-white leading-none tabular-nums">
                            {{ restDisplay }}
                        </p>
                    </div>

                    <!-- Arc progress bar -->
                    <div class="mt-6 h-2 overflow-hidden rounded-full bg-gray-800">
                        <div
                            class="h-full rounded-full bg-gradient-to-r from-violet-500 to-violet-300 transition-all duration-1000"
                            :style="{ width: restPercent + '%' }"
                        />
                    </div>

                    <!-- Controls -->
                    <div class="mt-6 flex flex-col gap-3">
                        <!-- End rest button (primary action) -->
                        <button
                            @click="endRest"
                            class="w-full rounded-2xl bg-gradient-to-r from-violet-600 to-violet-500 py-4 text-base font-bold text-white shadow-lg shadow-violet-500/30 transition hover:from-violet-700 hover:to-violet-600 active:scale-[0.98]"
                        >
                            Encerrar Descanso
                        </button>
                        <!-- Add time -->
                        <button
                            @click="addRestTime(30)"
                            class="w-full rounded-2xl border border-gray-700 bg-gray-900/80 py-3 text-sm font-semibold text-gray-300 transition hover:border-gray-600 hover:text-white active:scale-[0.98]"
                        >
                            + 30 segundos
                        </button>
                    </div>

                    <!-- Next set hint -->
                    <p
                        v-if="nextWaitingSet(currentExercise)"
                        class="mt-4 text-center text-xs text-gray-500"
                    >
                        Próxima: Série {{ nextWaitingSet(currentExercise) }}
                    </p>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- ─── Exercise Drawer / Overview ──────────────────────────────────── -->
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="showDrawer"
                class="fixed inset-0 z-[60] flex items-end justify-center bg-black/70 backdrop-blur-sm"
                @click.self="showDrawer = false"
            >
                <Transition name="slide-up">
                    <div
                        v-if="showDrawer"
                        class="w-full max-w-lg rounded-t-3xl bg-gray-900 border-t border-gray-700 p-5 max-h-[70vh] overflow-y-auto"
                    >
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-bold text-white">Visão Geral do Treino</h3>
                            <button @click="showDrawer = false" class="text-gray-400 hover:text-white transition">✕</button>
                        </div>
                        <ul class="space-y-2">
                            <li
                                v-for="(ex, idx) in sortedExercises"
                                :key="ex.id"
                                @click="navigateTo(idx)"
                                :class="[
                                    'flex items-center gap-3 rounded-2xl border p-3 cursor-pointer transition',
                                    idx === currentIndex
                                        ? 'border-violet-500 bg-violet-500/10'
                                        : isExerciseDone(ex)
                                          ? 'border-emerald-500/30 bg-emerald-500/10'
                                          : 'border-gray-800 hover:border-gray-700',
                                ]"
                            >
                                <span :class="[
                                    'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                                    isExerciseDone(ex)
                                        ? 'bg-emerald-500 text-white'
                                        : idx === currentIndex
                                          ? 'bg-violet-600 text-white'
                                          : 'bg-gray-800 text-gray-400',
                                ]">
                                    {{ isExerciseDone(ex) ? '✓' : (idx + 1) }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-white truncate">{{ ex.name }}</p>
                                    <p class="text-xs text-gray-500">{{ ex.primary_muscle }}</p>
                                </div>
                                <p class="text-xs text-gray-500 shrink-0">
                                    {{ savedSets[ex.id]?.size ?? 0 }}/{{ totalSetsForExercise(ex) }}
                                </p>
                            </li>
                        </ul>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>

    <!-- ─── Navigation Guard Modal ──────────────────────────────────────── -->
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="showNavGuard"
                class="fixed inset-0 z-[70] flex items-center justify-center bg-black/80 backdrop-blur-sm px-4"
            >
                <div class="w-full max-w-sm rounded-3xl border border-gray-700 bg-gray-900 p-6">
                    <h3 class="text-lg font-bold text-white">Série em Execução</h3>
                    <p class="mt-2 text-sm text-gray-400">
                        Você já iniciou este exercício. Deseja finalizar a série ou trocar de exercício e zerar o cronômetro?
                    </p>
                    <div class="mt-5 flex flex-col gap-2">
                        <button
                            @click="showNavGuard = false; pendingNavIndex = null"
                            class="w-full rounded-2xl bg-violet-600 py-3 text-sm font-bold text-white transition hover:bg-violet-700"
                        >
                            Continuar Série
                        </button>
                        <button
                            @click="confirmNavigation"
                            class="w-full rounded-2xl bg-gray-700 py-3 text-sm font-bold text-white transition hover:bg-gray-600"
                        >
                            Trocar e Zerar Timer
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- ─── Finish Modal ─────────────────────────────────────────────────── -->
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="showFinishModal"
                class="fixed inset-0 z-[70] flex items-end justify-center bg-black/80 backdrop-blur-sm px-4 pb-6"
            >
                <div class="w-full max-w-sm rounded-3xl border border-gray-700 bg-gray-900 p-6">
                    <div class="text-center mb-5">
                        <p class="text-4xl">🏆</p>
                        <h3 class="mt-2 text-xl font-bold text-white">Finalizar Treino?</h3>
                        <p class="mt-1 text-sm text-gray-400">Confira seu resumo antes de confirmar</p>
                    </div>

                    <div class="grid grid-cols-3 gap-3 mb-6">
                        <div class="rounded-2xl bg-gray-800 p-3 text-center">
                            <p class="text-xs text-gray-500">Duração</p>
                            <p class="mt-1 font-mono text-base font-bold text-violet-400">{{ elapsedDisplay }}</p>
                        </div>
                        <div class="rounded-2xl bg-gray-800 p-3 text-center">
                            <p class="text-xs text-gray-500">Séries</p>
                            <p class="mt-1 text-base font-bold text-emerald-400">{{ totalDoneSets }}</p>
                        </div>
                        <div class="rounded-2xl bg-gray-800 p-3 text-center">
                            <p class="text-xs text-gray-500">Volume</p>
                            <p class="mt-1 text-base font-bold text-orange-400">
                                {{ totalVolume >= 1000
                                    ? (totalVolume / 1000).toFixed(2) + 't'
                                    : totalVolume.toFixed(0) + 'kg' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <button
                            @click="finishWorkout"
                            :disabled="finishing"
                            class="flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-orange-500 to-red-500 py-4 text-base font-bold text-white shadow-lg shadow-orange-500/30 transition hover:from-orange-600 hover:to-red-600 disabled:opacity-60"
                        >
                            <svg v-if="finishing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            {{ finishing ? 'Finalizando...' : '🔥 Confirmar e Finalizar' }}
                        </button>
                        <button
                            @click="showFinishModal = false"
                            class="w-full rounded-2xl border border-gray-700 py-3 text-sm font-semibold text-gray-400 transition hover:border-gray-600 hover:text-white"
                        >
                            Voltar ao Treino
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.slide-up-enter-active,
.slide-up-leave-active {
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.slide-up-enter-from,
.slide-up-leave-to {
    opacity: 0;
    transform: translateY(20px);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
