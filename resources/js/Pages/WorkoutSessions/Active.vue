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

// ─── Exercise timer ───────────────────────────────────────────────────────────
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

// ─── Sets state (per exercise, per set number) ────────────────────────────────
// setInputs[exerciseId][setNumber] = { weight, reps }
const setInputs = ref({});
// Which set is "active" (focused) per exercise
const activeSetNumber = ref({});
// Which sets have been saved
const savedSets = ref({}); // { [exerciseId]: Set<setNumber> }

const initSetsForExercise = (exercise) => {
    const exId = exercise.id;
    if (!setInputs.value[exId]) {
        setInputs.value[exId] = {};
    }
    if (!savedSets.value[exId]) {
        savedSets.value[exId] = new Set();
    }

    // Pre-fill from existing setLogs
    const logsForExercise = props.setLogs.filter((l) => l.exercise_id === exId);
    for (const log of logsForExercise) {
        setInputs.value[exId][log.set_number] = {
            weight: log.weight ?? '',
            reps: log.reps ?? '',
        };
        savedSets.value[exId].add(log.set_number);
    }

    // Default first set input if not pre-filled
    const targetSets = exercise.target_sets ?? 3;
    for (let n = 1; n <= targetSets; n++) {
        if (!setInputs.value[exId][n]) {
            const last = props.lastLogs?.[exId];
            setInputs.value[exId][n] = {
                weight: last?.weight ?? '',
                reps: last?.reps ?? '',
            };
        }
    }

    // Track extra sets added dynamically
    if (!extraSets.value[exId]) {
        extraSets.value[exId] = 0;
    }

    // First active set is the first unsaved one
    if (!activeSetNumber.value[exId]) {
        const firstUnsaved = getFirstUnsavedSet(exId, exercise.target_sets ?? 3);
        activeSetNumber.value[exId] = firstUnsaved ?? 1;
    }
};

// ─── Extra sets ───────────────────────────────────────────────────────────────
const extraSets = ref({});

const totalSetsForExercise = (exercise) => {
    return (exercise.target_sets ?? 3) + (extraSets.value[exercise.id] ?? 0);
};

const addExtraSet = (exercise) => {
    const exId = exercise.id;
    extraSets.value[exId] = (extraSets.value[exId] ?? 0) + 1;
    const newSetNum = totalSetsForExercise(exercise);
    const last = props.lastLogs?.[exId];
    setInputs.value[exId][newSetNum] = {
        weight: last?.weight ?? '',
        reps: last?.reps ?? '',
    };
    activeSetNumber.value[exId] = newSetNum;
};

// ─── Helpers ──────────────────────────────────────────────────────────────────
const getFirstUnsavedSet = (exId, targetSets) => {
    const total = (targetSets ?? 3) + (extraSets.value[exId] ?? 0);
    for (let n = 1; n <= total; n++) {
        if (!savedSets.value[exId]?.has(n)) return n;
    }
    return null;
};

const isSetSaved = (exId, setNumber) => savedSets.value[exId]?.has(setNumber) ?? false;

const isExerciseDone = (exercise) => {
    const total = totalSetsForExercise(exercise);
    for (let n = 1; n <= total; n++) {
        if (!isSetSaved(exercise.id, n)) return false;
    }
    return total > 0;
};

// ─── Progress ─────────────────────────────────────────────────────────────────
const totalPlannedSets = computed(() =>
    sortedExercises.value.reduce((sum, ex) => sum + totalSetsForExercise(ex), 0),
);

const totalDoneSets = computed(() =>
    sortedExercises.value.reduce((sum, ex) => {
        return sum + (savedSets.value[ex.id]?.size ?? 0);
    }, 0),
);

const progressPercent = computed(() =>
    totalPlannedSets.value > 0
        ? Math.round((totalDoneSets.value / totalPlannedSets.value) * 100)
        : 0,
);

// ─── Total volume ─────────────────────────────────────────────────────────────
const totalVolume = computed(() => {
    return props.setLogs.reduce((sum, log) => {
        if (log.weight && log.reps) {
            return sum + parseFloat(log.weight) * parseInt(log.reps);
        }
        return sum;
    }, 0);
});

// ─── Navigation ───────────────────────────────────────────────────────────────
const hasPendingSets = (index) => {
    const ex = sortedExercises.value[index];
    if (!ex) return false;
    return exerciseTimerActive.value && !isExerciseDone(ex);
};

const tryNavigateTo = (index) => {
    if (index < 0 || index >= totalExercises.value) return;
    if (hasPendingSets(currentIndex.value)) {
        pendingNavIndex.value = index;
        showNavGuard.value = true;
        return;
    }
    navigateTo(index);
};

const navigateTo = (index) => {
    stopExerciseTimer();
    currentIndex.value = index;
    showNavGuard.value = false;
    pendingNavIndex.value = null;
    showDrawer.value = false;
    initSetsForExercise(sortedExercises.value[index]);
};

const confirmNavigation = () => {
    if (pendingNavIndex.value !== null) {
        navigateTo(pendingNavIndex.value);
    }
};

// ─── Rest timer ───────────────────────────────────────────────────────────────
const restActive = ref(false);
const restTotal = ref(60);
const restRemaining = ref(60);
const restPaused = ref(false);
let restInterval = null;

const restPercent = computed(() =>
    restTotal.value > 0 ? (restRemaining.value / restTotal.value) * 100 : 0,
);

const startRest = (seconds = 60) => {
    clearInterval(restInterval);
    restTotal.value = seconds;
    restRemaining.value = seconds;
    restPaused.value = false;
    restActive.value = true;

    restInterval = setInterval(() => {
        if (restPaused.value) return;
        restRemaining.value--;
        if (restRemaining.value <= 0) {
            clearInterval(restInterval);
            restActive.value = false;
            playChime();
            if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
        }
    }, 1000);
};

const toggleRestPause = () => { restPaused.value = !restPaused.value; };
const addRestTime = (secs) => { restRemaining.value += secs; restTotal.value += secs; };
const skipRest = () => { clearInterval(restInterval); restActive.value = false; };

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

// ─── Save set ─────────────────────────────────────────────────────────────────
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
                // Mark as saved
                if (!savedSets.value[exId]) savedSets.value[exId] = new Set();
                savedSets.value[exId].add(setNumber);
                savingSet.value = null;

                // Start rest timer
                const restSecs = exercise.rest_seconds ?? 60;
                startRest(restSecs);

                // Advance to next set
                const total = totalSetsForExercise(exercise);
                const nextUnsaved = getFirstUnsavedSet(exId, exercise.target_sets ?? 3);
                if (nextUnsaved && nextUnsaved <= total) {
                    activeSetNumber.value[exId] = nextUnsaved;
                }
            },
            onError: () => {
                savingSet.value = null;
            },
        },
    );
};

// ─── Quick weight adjustments ─────────────────────────────────────────────────
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

    // Init all exercises upfront
    sortedExercises.value.forEach((ex) => initSetsForExercise(ex));
});

onUnmounted(() => {
    clearInterval(workoutTimerInterval);
    clearInterval(exerciseTimerInterval);
    clearInterval(restInterval);
});

// Sync saved sets when props.setLogs change (after Inertia reload)
watch(
    () => props.setLogs,
    (newLogs) => {
        for (const log of newLogs) {
            if (!savedSets.value[log.exercise_id]) {
                savedSets.value[log.exercise_id] = new Set();
            }
            savedSets.value[log.exercise_id].add(log.set_number);
        }
    },
    { deep: true },
);
</script>

<template>
    <Head :title="`🔥 ${workout.name}`" />

    <!-- ─── Workout Elapsed Timer Bar ─────────────────────────────────── -->
    <div class="fixed inset-0 flex flex-col bg-gray-950 text-white" style="z-index: 50">

        <!-- Top Header -->
        <header class="flex-shrink-0 border-b border-gray-800 bg-gray-900/80 backdrop-blur-sm px-4 py-3">
            <div class="flex items-center justify-between gap-3">
                <!-- Left: workout name + elapsed -->
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-violet-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-violet-500"></span>
                        </span>
                        <p class="truncate text-sm font-bold text-white">{{ workout.name }}</p>
                    </div>
                    <p class="mt-0.5 text-xs font-mono text-violet-400">⏱ {{ elapsedDisplay }}</p>
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
                <!-- Prev button -->
                <button
                    @click="tryNavigateTo(currentIndex - 1)"
                    :disabled="currentIndex === 0"
                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-gray-800 text-gray-400 transition hover:bg-gray-700 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>

                <!-- Pills -->
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

                <!-- Next button -->
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

            <!-- Exercise name & position -->
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
                    <div class="min-w-0">
                        <h2 class="text-xl font-bold text-white truncate">{{ currentExercise.name }}</h2>
                        <p class="mt-0.5 text-sm text-gray-400">{{ currentExercise.primary_muscle }}</p>
                    </div>

                    <!-- Exercise timer -->
                    <div class="flex-shrink-0 text-right">
                        <button
                            v-if="!exerciseTimerActive"
                            @click="startExerciseTimer"
                            class="flex items-center gap-1.5 rounded-xl bg-gray-800 px-3 py-2 text-xs font-semibold text-gray-300 transition hover:bg-violet-500/20 hover:text-violet-300"
                        >
                            <span>▶</span> Cronometrar
                        </button>
                        <div v-else class="text-center">
                            <p class="font-mono text-lg font-bold text-violet-400">{{ exerciseTimerDisplay }}</p>
                            <button
                                @click="stopExerciseTimer"
                                class="mt-0.5 text-xs text-gray-500 hover:text-red-400 transition"
                            >
                                ✕ parar
                            </button>
                        </div>
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
                    <!-- Saved set (collapsed green) -->
                    <div
                        v-if="isSetSaved(currentExercise.id, n)"
                        class="flex items-center gap-3 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3"
                    >
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-500 text-sm font-bold text-white">
                            ✓
                        </span>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-emerald-400">Série {{ n }}</p>
                            <p class="text-xs text-gray-500">
                                {{ setInputs[currentExercise.id]?.[n]?.weight || '—' }}kg
                                × {{ setInputs[currentExercise.id]?.[n]?.reps || '—' }} reps
                            </p>
                        </div>
                        <span class="text-emerald-500 text-lg">✓</span>
                    </div>

                    <!-- Active set (expanded) -->
                    <div
                        v-else-if="n === activeSetNumber[currentExercise.id]"
                        class="rounded-2xl border border-violet-500/50 bg-gray-900 p-4"
                    >
                        <div class="flex items-center justify-between mb-4">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-violet-600 text-sm font-bold text-white">
                                {{ n }}
                            </span>
                            <p class="text-sm font-semibold text-violet-300">Série {{ n }} — Ativa</p>
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

                        <!-- Complete button -->
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

                    <!-- Locked set (future) -->
                    <div
                        v-else
                        class="flex items-center gap-3 rounded-2xl border border-gray-800 bg-gray-900/50 px-4 py-3 opacity-50 cursor-pointer hover:opacity-70 transition"
                        @click="activeSetNumber[currentExercise.id] = n"
                    >
                        <span class="flex h-7 w-7 items-center justify-center rounded-full border border-gray-700 text-xs font-bold text-gray-500">
                            {{ n }}
                        </span>
                        <p class="text-sm text-gray-500">Série {{ n }}</p>
                    </div>
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

                <!-- Add extra set -->
                <button
                    @click="addExtraSet(currentExercise)"
                    class="flex w-full items-center justify-center gap-2 rounded-2xl border border-dashed border-gray-700 py-2.5 text-sm text-gray-400 transition hover:border-violet-500/50 hover:text-violet-400"
                >
                    + Adicionar série extra
                </button>
            </div>

            <!-- Not done yet: add extra set option -->
            <button
                v-else-if="totalSetsForExercise(currentExercise) < 10"
                @click="addExtraSet(currentExercise)"
                class="mt-4 flex w-full items-center justify-center gap-2 rounded-2xl border border-dashed border-gray-700 py-2.5 text-sm text-gray-400 transition hover:border-violet-500/50 hover:text-violet-400"
            >
                + Adicionar série extra
            </button>
        </main>

        <!-- ─── Floating Rest Timer ──────────────────────────────────────── -->
        <Transition name="slide-up">
            <div
                v-if="restActive"
                class="absolute bottom-24 left-1/2 -translate-x-1/2 w-[calc(100%-2rem)] max-w-sm z-60"
            >
                <div class="rounded-2xl border border-violet-500/40 bg-gray-900/95 backdrop-blur-md p-4 shadow-2xl shadow-violet-500/20">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-violet-400">⏸ Descanso</p>
                        <button @click="skipRest" class="text-xs text-gray-500 hover:text-red-400 transition">Pular</button>
                    </div>

                    <!-- Countdown -->
                    <div class="text-center">
                        <p class="font-mono text-5xl font-bold text-white leading-none">{{ restDisplay }}</p>
                    </div>

                    <!-- Progress bar -->
                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-800">
                        <div
                            class="h-full rounded-full bg-gradient-to-r from-violet-500 to-violet-300 transition-all duration-1000"
                            :style="{ width: restPercent + '%' }"
                        />
                    </div>

                    <!-- Controls -->
                    <div class="mt-3 flex items-center justify-center gap-3">
                        <button
                            @click="addRestTime(30)"
                            class="rounded-xl bg-gray-800 px-4 py-2 text-sm font-semibold text-gray-300 transition hover:bg-gray-700 hover:text-white"
                        >+30s</button>
                        <button
                            @click="toggleRestPause"
                            class="rounded-xl bg-violet-600 px-5 py-2 text-sm font-bold text-white transition hover:bg-violet-700"
                        >{{ restPaused ? '▶ Retomar' : '⏸ Pausar' }}</button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- ─── Bottom Footer ────────────────────────────────────────────── -->
        <footer class="absolute bottom-0 left-0 right-0 border-t border-gray-800 bg-gray-900/95 backdrop-blur-sm px-4 py-3 z-50">
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
                    <h3 class="text-lg font-bold text-white">Exercício em Andamento</h3>
                    <p class="mt-2 text-sm text-gray-400">
                        Você tem séries não concluídas neste exercício. Deseja descartar e avançar mesmo assim?
                    </p>
                    <div class="mt-5 flex flex-col gap-2">
                        <button
                            @click="confirmNavigation"
                            class="w-full rounded-2xl bg-gray-700 py-3 text-sm font-bold text-white transition hover:bg-gray-600"
                        >
                            Descartar e Avançar
                        </button>
                        <button
                            @click="showNavGuard = false; pendingNavIndex = null"
                            class="w-full rounded-2xl bg-violet-600 py-3 text-sm font-bold text-white transition hover:bg-violet-700"
                        >
                            Continuar Exercício
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
                        <h3 class="mt-2 text-xl font-bold text-white">Treino Finalizado!</h3>
                        <p class="mt-1 text-sm text-gray-400">Confira seu resumo</p>
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
