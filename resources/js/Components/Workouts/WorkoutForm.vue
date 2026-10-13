<script setup>
import ExerciseSelector from '@/Components/Workouts/ExerciseSelector.vue';
import SupersetBlock from '@/Components/Workouts/SupersetBlock.vue';
import WeekdaySelector from '@/Components/Workouts/WeekdaySelector.vue';
import WorkoutFormExerciseItem from '@/Components/Workouts/WorkoutFormExerciseItem.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { useExerciseReorder } from '@/Composables/useExerciseReorder';
import {
    buildExerciseBlocks,
    joinWithPrevious,
    removeExerciseAt,
    separateFromGroup,
} from '@/Utils/supersetGroups';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    // The Inertia `useForm` instance owned by the parent page (Create/Edit).
    // Mutated directly here, the same way a v-model target would be.
    form: {
        type: Object,
        required: true,
    },
    exercisesCatalog: {
        type: Array,
        default: () => [],
    },
    cancelHref: {
        type: String,
        required: true,
    },
    submitLabel: {
        type: String,
        default: 'Salvar Treino',
    },
    processingLabel: {
        type: String,
        default: 'Salvando Treino...',
    },
});

const emit = defineEmits(['submit']);

const showExerciseSelector = ref(false);

const selectedExerciseIds = computed(() =>
    props.form.exercises.map((exercise) => exercise.id),
);

const openExerciseSelector = () => {
    showExerciseSelector.value = true;
};

const closeExerciseSelector = () => {
    showExerciseSelector.value = false;
};

const addExercise = (exercise) => {
    if (selectedExerciseIds.value.includes(exercise.id)) {
        return;
    }

    props.form.exercises.push({
        id: exercise.id,
        name: exercise.name,
        primary_muscle: exercise.primary_muscle,
        target_sets: 3,
        target_reps: '10',
        order: props.form.exercises.length,
        superset_group: null,
    });
};

// Blocos para exibir: exercício sozinho = bloco de 1; bi-set/tri-set = bloco
// com rótulo. Regra de mover/juntar/separar: `Utils/supersetGroups.js`.
const blocks = computed(() => buildExerciseBlocks(props.form.exercises));

const setExercises = (list) => {
    props.form.exercises = list;
};

const removeExercise = (index) => {
    setExercises(removeExerciseAt(props.form.exercises, index));
};

const joinExercise = (index) => {
    setExercises(joinWithPrevious(props.form.exercises, index));
};

const separateExercise = (index) => {
    setExercises(separateFromGroup(props.form.exercises, index));
};

const removeExerciseById = (exercise) => {
    const index = props.form.exercises.findIndex((e) => e.id === exercise.id);
    if (index !== -1) {
        removeExercise(index);
    }
};

const toggleExercise = (exercise) => {
    const index = props.form.exercises.findIndex((e) => e.id === exercise.id);
    if (index !== -1) {
        removeExercise(index);
    } else {
        addExercise(exercise);
    }
};

// ─── Drag & Drop + setas ─────────────────────────────────────────────────────
// Sozinho pula o bloco vizinho inteiro; dentro de um grupo só troca a ordem
// interna; arrastar um exercício do grupo para fora leva o bloco inteiro.
const {
    draggingIndex,
    dragOverIndex,
    onDragStart,
    onDragOver,
    onDragEnd,
    onDrop,
    canMove,
    move,
    canMoveBlock,
    moveBlock,
} = useExerciseReorder(() => props.form.exercises, setExercises);

const blockContains = (block, index) => index !== null && block.items.some((item) => item.index === index);

// Algo de fora do bloco está sendo arrastado sobre ele.
const isBlockHighlighted = (block) =>
    blockContains(block, dragOverIndex.value) && !blockContains(block, draggingIndex.value);
</script>

<template>
    <form @submit.prevent="emit('submit')" class="space-y-6">
        <!-- Informações Básicas do Treino -->
        <div
            class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-800/90"
        >
            <h3
                class="text-base font-bold text-gray-900 dark:text-gray-100"
            >
                Informações Gerais
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Dê um nome representativo para sua ficha de treino.
            </p>

            <div class="mt-5 space-y-4">
                <div>
                    <InputLabel
                        for="name"
                        value="Nome do Treino"
                        class="font-semibold text-gray-700 dark:text-gray-300"
                    />
                    <TextInput
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="mt-1.5 block w-full rounded-xl focus:border-violet-500 focus:ring-violet-500 dark:focus:border-violet-500 dark:focus:ring-violet-500"
                        placeholder="Ex: Treino A - Peito e Tríceps"
                        required
                    />
                    <InputError
                        class="mt-1.5"
                        :message="form.errors.name"
                    />
                </div>

                <div>
                    <InputLabel
                        for="description"
                        value="Descrição ou Observações (opcional)"
                        class="font-semibold text-gray-700 dark:text-gray-300"
                    />
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="2"
                        class="mt-1.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-violet-500 dark:focus:ring-violet-500 text-sm"
                        placeholder="Ex: Foco em hipertrofia, descanso de 60-90s entre séries..."
                    />
                    <InputError
                        class="mt-1.5"
                        :message="form.errors.description"
                    />
                </div>

                <div>
                    <InputLabel
                        value="Dias da Semana (opcional)"
                        class="font-semibold text-gray-700 dark:text-gray-300"
                    />
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Selecione os dias em que este treino costuma ser realizado.
                    </p>
                    <WeekdaySelector
                        v-model="form.days_of_week"
                        class="mt-2"
                    />
                    <InputError
                        class="mt-1.5"
                        :message="form.errors.days_of_week"
                    />
                </div>
            </div>
        </div>

        <!-- Lista de Exercícios -->
        <div
            class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-800/90"
        >
            <div
                class="pb-4 border-b border-gray-100 dark:border-gray-700"
            >
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <h3
                            class="text-base font-bold text-gray-900 dark:text-gray-100"
                        >
                            Exercícios do Treino
                        </h3>
                        <span
                            v-if="form.exercises.length > 0"
                            class="rounded-full bg-violet-500/10 px-2.5 py-0.5 text-xs font-bold text-violet-600 dark:text-violet-400"
                        >
                            {{ form.exercises.length }} selecionado(s)
                        </span>
                    </div>
                    <p
                        v-if="form.exercises.length > 1"
                        class="text-xs text-gray-400 dark:text-gray-500"
                    >
                        Arraste para reordenar
                    </p>
                </div>
                <p
                    class="mt-0.5 text-sm text-gray-500 dark:text-gray-400"
                >
                    Selecione exercícios e configure as metas de séries e repetições.
                </p>
            </div>

            <!-- Empty state when no exercise selected -->
            <div
                v-if="form.exercises.length === 0"
                class="mt-6 rounded-2xl border-2 border-dashed border-gray-200 py-12 text-center dark:border-gray-700"
            >
                <div
                    class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-violet-500/10 text-2xl text-violet-600 dark:text-violet-400"
                >
                    🏋️‍♂️
                </div>
                <h4
                    class="text-base font-semibold text-gray-800 dark:text-gray-200"
                >
                    Nenhum exercício adicionado ainda
                </h4>
                <p
                    class="mx-auto mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400"
                >
                    Toque no botão abaixo para explorar o catálogo de grupos musculares e adicionar exercícios.
                </p>
                <button
                    type="button"
                    @click="openExerciseSelector"
                    class="mt-5 inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-violet-500/20 transition hover:bg-violet-700"
                >
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2.5"
                            d="M12 4v16m8-8H4"
                        />
                    </svg>
                    <span>Explorar Catálogo de Exercícios</span>
                </button>
            </div>

            <!-- Exercises list with drag & drop -->
            <div v-else class="mt-6 space-y-4">
                <TransitionGroup
                    tag="div"
                    name="exercise-list"
                    class="space-y-3"
                >
                    <SupersetBlock
                        v-for="(block, blockIndex) in blocks"
                        :key="block.key"
                        :label="block.label"
                        :can-move-up="canMoveBlock(blockIndex, -1)"
                        :can-move-down="canMoveBlock(blockIndex, 1)"
                        :highlighted="isBlockHighlighted(block)"
                        @move-up="moveBlock(blockIndex, -1)"
                        @move-down="moveBlock(blockIndex, 1)"
                    >
                        <ul class="space-y-2">
                            <WorkoutFormExerciseItem
                                v-for="{ exercise, index } in block.items"
                                :key="exercise.id"
                                :exercise="exercise"
                                :number="index + 1"
                                :can-move-up="canMove(index, -1)"
                                :can-move-down="canMove(index, 1)"
                                :can-join="blockIndex > 0 && block.items[0].index === index"
                                :grouped="block.label !== null"
                                :dragging="draggingIndex === index"
                                :drag-over="dragOverIndex === index && draggingIndex !== index && !isBlockHighlighted(block)"
                                draggable="true"
                                @dragstart="(e) => onDragStart(e, index)"
                                @dragover="(e) => onDragOver(e, index)"
                                @drop="onDrop(index)"
                                @dragend="onDragEnd"
                                @move-up="move(index, -1)"
                                @move-down="move(index, 1)"
                                @remove="removeExercise(index)"
                                @join="joinExercise(index)"
                                @separate="separateExercise(index)"
                            />
                        </ul>
                    </SupersetBlock>
                </TransitionGroup>

                <button
                    type="button"
                    @click="openExerciseSelector"
                    class="w-full inline-flex items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-violet-500/40 py-3.5 text-sm font-bold text-violet-600 transition hover:border-violet-600 hover:bg-violet-500/5 dark:text-violet-400 dark:hover:bg-violet-500/10"
                >
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2.5"
                            d="M12 4v16m8-8H4"
                        />
                    </svg>
                    <span>+ Adicionar Exercício</span>
                </button>
            </div>

            <InputError
                class="mt-3"
                :message="form.errors.exercises"
            />
        </div>

        <!-- Bottom Action Buttons -->
        <div
            class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end pt-2"
        >
            <Link
                :href="cancelHref"
                class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-5 py-3 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
            >
                Cancelar
            </Link>

            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-violet-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-violet-500/25 transition hover:bg-violet-700 hover:shadow-violet-500/40 focus:outline-none focus:ring-2 focus:ring-violet-500 disabled:opacity-50"
            >
                <svg
                    v-if="form.processing"
                    class="h-4 w-4 animate-spin text-white"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <circle
                        class="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        stroke-width="4"
                    />
                    <path
                        class="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    />
                </svg>
                <span>{{ form.processing ? processingLabel : submitLabel }}</span>
            </button>
        </div>
    </form>

    <!-- Fullscreen Exercise Selector -->
    <ExerciseSelector
        :show="showExerciseSelector"
        :catalog="exercisesCatalog"
        :selected-ids="selectedExerciseIds"
        @close="closeExerciseSelector"
        @select="addExercise"
        @remove="removeExerciseById"
        @toggle="toggleExercise"
    />
</template>

<style scoped>
.exercise-list-enter-active,
.exercise-list-leave-active {
    transition: all 0.25s ease;
}

.exercise-list-enter-from,
.exercise-list-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}
</style>
