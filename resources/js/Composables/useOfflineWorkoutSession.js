import { computed, ref } from 'vue';
import { useOfflineSync } from '@/Composables/useOfflineSync';
import { generateUuid, getCachedLastLogs } from '@/Utils/offlineStorage';

/**
 * Máquina de estados de uma sessão de treino 100% offline: a mesma lógica é
 * usada tanto por `Workouts/Show` (que já está com o treino aberto) quanto
 * por `Workouts/Index` (que abre um treino cacheado direto da lista, sem
 * conexão). Mora aqui — e não em `OfflineSessionPanel.vue` — porque
 * componentes em `resources/js/Components/` são "burros": só recebem dados
 * via props e emitem eventos, quem coordena a fila de sincronização é sempre
 * a Página (ver `.ai/rules/vue-inertia-architecture.md`).
 *
 * Cada chamada a este composable cria um estado independente (não é estado
 * compartilhado como `useOfflineSync`), então cada Página que o invoca tem
 * sua própria sessão offline em andamento.
 */
export function useOfflineWorkoutSession() {
    const { queueAction } = useOfflineSync();

    const workout = ref(null);
    const clientSessionId = ref(null);

    const sortedExercises = computed(() =>
        workout.value ? [...workout.value.exercises].sort((a, b) => (a.order ?? 0) - (b.order ?? 0)) : [],
    );

    const currentIndex = ref(0);
    const currentExercise = computed(() => sortedExercises.value[currentIndex.value] ?? null);

    const lastLogs = ref({});
    const setInputs = ref({});
    const savedSets = ref({});
    const finished = ref(false);
    const finishing = ref(false);

    const initExercise = (exercise) => {
        if (!exercise) return;
        const exId = exercise.id;
        if (!setInputs.value[exId]) setInputs.value[exId] = {};
        if (!savedSets.value[exId]) savedSets.value[exId] = new Set();

        const targetSets = exercise.target_sets ?? 3;
        const last = lastLogs.value[exId];

        for (let n = 1; n <= targetSets; n++) {
            if (setInputs.value[exId][n]) continue;

            const previous = setInputs.value[exId][n - 1];
            setInputs.value[exId][n] = {
                weight: previous?.weight ?? last?.weight ?? 0,
                reps: previous?.reps ?? last?.reps ?? exercise.target_reps ?? '',
            };
        }
    };

    const isSetSaved = (exId, n) => savedSets.value[exId]?.has(n) ?? false;

    const currentSetNumbers = computed(() =>
        currentExercise.value ? Array.from({ length: currentExercise.value.target_sets ?? 3 }, (_, i) => i + 1) : [],
    );

    const currentSetInputs = computed(() => setInputs.value[currentExercise.value?.id] ?? {});

    const currentSets = computed(() =>
        currentSetNumbers.value.map((number) => ({
            number,
            saved: isSetSaved(currentExercise.value?.id, number),
        })),
    );

    const doneSetsCount = computed(() =>
        Object.values(savedSets.value).reduce((sum, set) => sum + set.size, 0),
    );

    /**
     * Inicia uma sessão offline para o treino informado: zera todo o estado
     * anterior, enfileira o `start_session` e carrega o histórico recente
     * cacheado para pré-preencher a Série 1.
     */
    const start = async (newWorkout) => {
        workout.value = newWorkout;
        clientSessionId.value = generateUuid();
        currentIndex.value = 0;
        setInputs.value = {};
        savedSets.value = {};
        finished.value = false;
        finishing.value = false;

        lastLogs.value = await getCachedLastLogs();
        initExercise(sortedExercises.value[0]);

        await queueAction({
            type: 'start_session',
            client_session_id: clientSessionId.value,
            occurred_at: new Date().toISOString(),
            workout_id: workout.value.id,
        });
    };

    const goToExercise = (index) => {
        if (index < 0 || index >= sortedExercises.value.length) return;
        currentIndex.value = index;
        initExercise(sortedExercises.value[index]);
    };

    const updateSetField = (setNumber, field, value) => {
        const exId = currentExercise.value?.id;
        if (!exId || !setInputs.value[exId]?.[setNumber]) return;
        setInputs.value[exId][setNumber][field] = value;
    };

    const saveSet = async (setNumber) => {
        const exercise = currentExercise.value;
        if (!exercise) return;
        const exId = exercise.id;
        const inputs = setInputs.value[exId][setNumber];

        await queueAction({
            type: 'log_set',
            client_session_id: clientSessionId.value,
            occurred_at: new Date().toISOString(),
            exercise_id: exId,
            set_number: setNumber,
            weight: inputs.weight !== '' ? Number(inputs.weight) : null,
            reps: Number(inputs.reps),
        });

        savedSets.value[exId].add(setNumber);

        const next = setInputs.value[exId][setNumber + 1];
        if (next && !isSetSaved(exId, setNumber + 1)) {
            next.weight = inputs.weight;
            next.reps = inputs.reps;
        }
    };

    const finishOffline = async () => {
        finishing.value = true;
        await queueAction({
            type: 'finish_session',
            client_session_id: clientSessionId.value,
            occurred_at: new Date().toISOString(),
        });
        finishing.value = false;
        finished.value = true;
    };

    const reset = () => {
        workout.value = null;
        clientSessionId.value = null;
        currentIndex.value = 0;
        setInputs.value = {};
        savedSets.value = {};
        finished.value = false;
        finishing.value = false;
    };

    return {
        workout,
        sortedExercises,
        currentIndex,
        currentExercise,
        currentSetInputs,
        currentSets,
        doneSetsCount,
        finished,
        finishing,
        start,
        goToExercise,
        updateSetField,
        saveSet,
        finishOffline,
        reset,
    };
}
