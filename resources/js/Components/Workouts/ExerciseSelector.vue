<script setup>
import Modal from '@/Components/Modal.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    catalog: {
        type: Array,
        default: () => [],
    },
    selectedIds: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close', 'select']);

const searchQuery = ref('');
const activeMuscleFilter = ref(null);

const muscleFilters = computed(() =>
    props.catalog.map((group) => ({
        key: group.muscle_key,
        label: group.muscle,
    })),
);

const allExercises = computed(() =>
    props.catalog.flatMap((group) =>
        group.exercises.map((exercise) => ({
            ...exercise,
            muscle_key: group.muscle_key,
            muscle: group.muscle,
        })),
    ),
);

const filteredExercises = computed(() => {
    let exercises = allExercises.value;

    if (activeMuscleFilter.value) {
        exercises = exercises.filter(
            (exercise) => exercise.muscle_key === activeMuscleFilter.value,
        );
    }

    const query = searchQuery.value.trim().toLowerCase();

    if (query) {
        exercises = exercises.filter((exercise) =>
            exercise.name.toLowerCase().includes(query),
        );
    }

    return exercises;
});

const isSelected = (exerciseId) => props.selectedIds.includes(exerciseId);

const selectExercise = (exercise) => {
    if (isSelected(exercise.id)) {
        return;
    }

    emit('select', exercise);
};

const close = () => {
    searchQuery.value = '';
    activeMuscleFilter.value = null;
    emit('close');
};

const setMuscleFilter = (muscleKey) => {
    activeMuscleFilter.value = muscleKey;
};
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="close">
        <div class="p-6">
            <div class="mb-5">
                <h3
                    class="text-lg font-semibold text-gray-900 dark:text-gray-100"
                >
                    Adicionar exercício
                </h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Busque ou filtre por grupo muscular e toque para adicionar.
                </p>
            </div>

            <div class="relative mb-4">
                <svg
                    class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                    />
                </svg>
                <input
                    v-model="searchQuery"
                    type="search"
                    placeholder="Buscar exercício..."
                    class="block w-full rounded-xl border-gray-300 py-2.5 pl-10 pr-4 text-sm shadow-sm transition focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-violet-500 dark:focus:ring-violet-500"
                />
            </div>

            <div class="mb-4 flex gap-2 overflow-x-auto pb-1">
                <button
                    type="button"
                    @click="setMuscleFilter(null)"
                    class="shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold transition"
                    :class="
                        activeMuscleFilter === null
                            ? 'bg-violet-500 text-white shadow-sm'
                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                    "
                >
                    Todos
                </button>
                <button
                    v-for="filter in muscleFilters"
                    :key="filter.key"
                    type="button"
                    @click="setMuscleFilter(filter.key)"
                    class="shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold transition"
                    :class="
                        activeMuscleFilter === filter.key
                            ? 'bg-violet-500 text-white shadow-sm'
                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                    "
                >
                    {{ filter.label }}
                </button>
            </div>

            <div
                class="max-h-72 space-y-2 overflow-y-auto rounded-xl border border-gray-200 p-2 dark:border-gray-700"
            >
                <button
                    v-for="exercise in filteredExercises"
                    :key="exercise.id"
                    type="button"
                    @click="selectExercise(exercise)"
                    :disabled="isSelected(exercise.id)"
                    class="flex w-full items-center justify-between rounded-lg px-3 py-3 text-left transition"
                    :class="
                        isSelected(exercise.id)
                            ? 'cursor-not-allowed bg-violet-500/10 opacity-60'
                            : 'hover:bg-gray-50 dark:hover:bg-gray-700/50'
                    "
                >
                    <div>
                        <p
                            class="text-sm font-medium text-gray-900 dark:text-gray-100"
                        >
                            {{ exercise.name }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ exercise.primary_muscle }}
                        </p>
                    </div>
                    <span
                        v-if="isSelected(exercise.id)"
                        class="text-xs font-semibold text-violet-500"
                    >
                        Adicionado
                    </span>
                    <svg
                        v-else
                        class="h-5 w-5 text-violet-500"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 4v16m8-8H4"
                        />
                    </svg>
                </button>

                <p
                    v-if="filteredExercises.length === 0"
                    class="py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                >
                    Nenhum exercício encontrado.
                </p>
            </div>

            <div class="mt-5 flex justify-end">
                <button
                    type="button"
                    @click="close"
                    class="rounded-xl bg-violet-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-violet-600"
                >
                    Concluir
                </button>
            </div>
        </div>
    </Modal>
</template>
