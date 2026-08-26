<script setup>
import ExerciseSelector from '@/Components/Workouts/ExerciseSelector.vue';
import WeekdaySelector from '@/Components/Workouts/WeekdaySelector.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
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
    });
};

const removeExercise = (index) => {
    props.form.exercises.splice(index, 1);
    props.form.exercises.forEach((exercise, order) => {
        exercise.order = order;
    });
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

// ─── Drag & Drop ─────────────────────────────────────────────────────────────
const draggingIndex = ref(null);
const dragOverIndex = ref(null);

// Ignore drag when clicking inside interactive elements
const DRAG_IGNORE = ['INPUT', 'TEXTAREA', 'SELECT', 'BUTTON', 'A', 'LABEL'];

const isDraggableTarget = (event) => {
    let el = event.target;
    while (el && el !== event.currentTarget) {
        if (DRAG_IGNORE.includes(el.tagName)) return false;
        el = el.parentElement;
    }
    return true;
};

const onDragStart = (event, index) => {
    if (!isDraggableTarget(event)) {
        event.preventDefault();
        return;
    }
    draggingIndex.value = index;
};

const onDragOver = (e, index) => {
    e.preventDefault();
    dragOverIndex.value = index;
};

const onDragEnd = () => {
    draggingIndex.value = null;
    dragOverIndex.value = null;
};

const onDrop = (targetIndex) => {
    if (draggingIndex.value === null || draggingIndex.value === targetIndex) {
        onDragEnd();
        return;
    }
    const arr = [...props.form.exercises];
    const [moved] = arr.splice(draggingIndex.value, 1);
    arr.splice(targetIndex, 0, moved);
    arr.forEach((ex, i) => { ex.order = i; });
    props.form.exercises = arr;
    onDragEnd();
};

const moveUp = (index) => {
    if (index === 0) return;
    const arr = [...props.form.exercises];
    [arr[index - 1], arr[index]] = [arr[index], arr[index - 1]];
    arr.forEach((ex, i) => { ex.order = i; });
    props.form.exercises = arr;
};

const moveDown = (index) => {
    if (index === props.form.exercises.length - 1) return;
    const arr = [...props.form.exercises];
    [arr[index], arr[index + 1]] = [arr[index + 1], arr[index]];
    arr.forEach((ex, i) => { ex.order = i; });
    props.form.exercises = arr;
};
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
                        autofocus
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
                    tag="ul"
                    name="exercise-list"
                    class="space-y-3"
                >
                    <li
                        v-for="(exercise, index) in form.exercises"
                        :key="exercise.id"
                        draggable="true"
                        @dragstart="(e) => onDragStart(e, index)"
                        @dragover="(e) => onDragOver(e, index)"
                        @drop="onDrop(index)"
                        @dragend="onDragEnd"
                        :class="[
                            'group rounded-2xl border p-4 transition-all duration-200',
                            dragOverIndex === index && draggingIndex !== index
                                ? 'border-violet-500 bg-violet-500/5 dark:bg-violet-500/10 cursor-copy'
                                : 'border-gray-200 bg-gray-50/70 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-900/50 cursor-grab active:cursor-grabbing',
                            draggingIndex === index ? 'opacity-40 scale-[0.98]' : 'opacity-100',
                        ]"
                    >
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="flex items-center gap-3 select-none">
                                <span
                                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-violet-600 text-xs font-bold text-white"
                                >
                                    {{ index + 1 }}
                                </span>
                                <div>
                                    <p
                                        class="text-sm font-bold text-gray-900 dark:text-gray-100"
                                    >
                                        {{ exercise.name }}
                                    </p>
                                    <span
                                        class="inline-block mt-0.5 rounded-md bg-gray-200/80 px-2 py-0.5 text-[11px] font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                                    >
                                        {{ exercise.primary_muscle }}
                                    </span>
                                </div>
                            </div>

                            <!-- Reorder + Remove buttons -->
                            <div class="flex items-center gap-1">
                                <div
                                    class="flex flex-col gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity"
                                >
                                    <button
                                        type="button"
                                        @click.stop="moveUp(index)"
                                        :disabled="index === 0"
                                        class="rounded-md p-1 text-gray-400 transition hover:bg-violet-500/10 hover:text-violet-500 disabled:opacity-20 disabled:cursor-not-allowed"
                                        title="Mover para cima"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7" />
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        @click.stop="moveDown(index)"
                                        :disabled="index === form.exercises.length - 1"
                                        class="rounded-md p-1 text-gray-400 transition hover:bg-violet-500/10 hover:text-violet-500 disabled:opacity-20 disabled:cursor-not-allowed"
                                        title="Mover para baixo"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                </div>

                                <!-- Trash icon (remove exercise) -->
                                <button
                                    type="button"
                                    @click.stop="removeExercise(index)"
                                    class="rounded-lg p-1.5 text-gray-400 transition hover:bg-red-500/10 hover:text-red-500 focus:outline-none"
                                    title="Remover exercício"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 pt-2 border-t border-gray-200/60 dark:border-gray-800">
                            <div>
                                <label
                                    class="block text-xs font-semibold text-gray-600 dark:text-gray-400"
                                >
                                    Séries
                                </label>
                                <input
                                    v-model.number="exercise.target_sets"
                                    type="number"
                                    min="1"
                                    max="20"
                                    placeholder="3"
                                    class="mt-1 block w-full rounded-xl border-gray-300 py-1.5 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                                />
                            </div>

                            <div>
                                <label
                                    class="block text-xs font-semibold text-gray-600 dark:text-gray-400"
                                >
                                    Repetições
                                </label>
                                <input
                                    v-model="exercise.target_reps"
                                    type="text"
                                    placeholder="10 ou 8-12"
                                    class="mt-1 block w-full rounded-xl border-gray-300 py-1.5 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                                />
                            </div>
                        </div>
                    </li>
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
