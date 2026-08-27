<script setup>
import ExerciseActiveCard from '@/Components/WorkoutSessions/ExerciseActiveCard.vue';
import ExerciseOverviewDrawer from '@/Components/WorkoutSessions/ExerciseOverviewDrawer.vue';
import ExerciseStepper from '@/Components/WorkoutSessions/ExerciseStepper.vue';
import FinishWorkoutModal from '@/Components/WorkoutSessions/FinishWorkoutModal.vue';
import NavigationGuardModal from '@/Components/WorkoutSessions/NavigationGuardModal.vue';
import RestTimerOverlay from '@/Components/WorkoutSessions/RestTimerOverlay.vue';
import SessionHeader from '@/Components/WorkoutSessions/SessionHeader.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps({
    session: { type: Object, required: true },
    workout: { type: Object, required: true },
    exercises: { type: Array, required: true },
    setLogs: { type: Array, default: () => [] },
    lastLogs: { type: Object, default: () => ({}) },
    overloadSuggestions: { type: Array, default: () => [] },
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
// dismissedSuggestions[exerciseId] = true once the user closes the card for it.
const dismissedSuggestions = ref({});

const currentOverloadSuggestion = computed(() => {
    if (dismissedSuggestions.value[currentExercise.value?.id]) return null;
    return props.overloadSuggestions.find((s) => s.exercise_name === currentExercise.value?.name) ?? null;
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
        <SessionHeader
            :workout-name="workout.name"
            :elapsed-display="elapsedDisplay"
            :exercise-timer-active="exerciseTimerActive"
            :exercise-timer-display="exerciseTimerDisplay"
            :total-done-sets="totalDoneSets"
            :total-planned-sets="totalPlannedSets"
            :progress-percent="progressPercent"
            @open-drawer="showDrawer = true"
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
</template>
