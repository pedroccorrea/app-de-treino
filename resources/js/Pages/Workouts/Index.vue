<script setup>
import ExerciseSelector from '@/Components/Workouts/ExerciseSelector.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    workouts: {
        type: Array,
        default: () => [],
    },
    exercisesCatalog: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const showCreateModal = ref(false);
const showExerciseSelector = ref(false);

const form = useForm({
    name: '',
    description: '',
    exercises: [],
});

const selectedExerciseIds = computed(() =>
    form.exercises.map((exercise) => exercise.id),
);

const hasWorkouts = computed(() => props.workouts.length > 0);

const openCreateModal = () => {
    form.clearErrors();
    showCreateModal.value = true;
};

const closeCreateModal = () => {
    showCreateModal.value = false;
    showExerciseSelector.value = false;
    form.reset();
    form.clearErrors();
};

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

    form.exercises.push({
        id: exercise.id,
        name: exercise.name,
        primary_muscle: exercise.primary_muscle,
        target_sets: 3,
        target_reps: '10',
        order: form.exercises.length,
    });
};

const removeExercise = (index) => {
    form.exercises.splice(index, 1);
    form.exercises.forEach((exercise, order) => {
        exercise.order = order;
    });
};

const submit = () => {
    form.post(route('workouts.store'), {
        preserveScroll: true,
        onSuccess: () => {
            closeCreateModal();
        },
    });
};
</script>

<template>
    <Head title="Treinos" />

    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <h2
                    class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                >
                    Treinos
                </h2>

                <button
                    type="button"
                    @click="openCreateModal"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-violet-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-600 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
                >
                    <svg
                        class="h-5 w-5"
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
                    Novo Treino
                </button>
            </div>
        </template>

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div
                    v-if="page.props.flash?.success"
                    class="mb-6 rounded-xl border border-violet-500/30 bg-violet-500/10 px-4 py-3 text-sm font-medium text-violet-700 dark:text-violet-300"
                >
                    {{ page.props.flash.success }}
                </div>

                <div
                    v-if="!hasWorkouts"
                    class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center dark:border-gray-700 dark:bg-gray-800"
                >
                    <div
                        class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-violet-500/10"
                    >
                        <svg
                            class="h-8 w-8 text-violet-500"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                            />
                        </svg>
                    </div>

                    <h3
                        class="text-lg font-semibold text-gray-900 dark:text-gray-100"
                    >
                        Você ainda não tem treinos cadastrados
                    </h3>
                    <p
                        class="mx-auto mt-2 max-w-md text-sm text-gray-600 dark:text-gray-400"
                    >
                        Crie sua primeira ficha de treino selecionando
                        exercícios do catálogo e organize sua rotina semanal.
                    </p>

                    <button
                        type="button"
                        @click="openCreateModal"
                        class="mt-6 inline-flex items-center gap-2 rounded-xl bg-violet-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-violet-600"
                    >
                        <svg
                            class="h-5 w-5"
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
                        Criar meu primeiro treino
                    </button>
                </div>

                <div
                    v-else
                    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <Link
                        v-for="workout in workouts"
                        :key="workout.id"
                        :href="route('workouts.show', workout.id)"
                        class="group block rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-violet-500/40 hover:shadow-md dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3
                                    class="text-lg font-semibold text-gray-900 transition group-hover:text-violet-600 dark:text-gray-100 dark:group-hover:text-violet-400"
                                >
                                    {{ workout.name }}
                                </h3>
                                <p
                                    v-if="workout.description"
                                    class="mt-1 line-clamp-2 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ workout.description }}
                                </p>
                            </div>

                            <span
                                class="inline-flex shrink-0 items-center rounded-full bg-violet-500/10 px-2.5 py-1 text-xs font-semibold text-violet-600 dark:text-violet-400"
                            >
                                {{ workout.exercises_count }}
                                {{
                                    workout.exercises_count === 1
                                        ? 'exercício'
                                        : 'exercícios'
                                }}
                            </span>
                        </div>

                        <div
                            v-if="workout.muscle_groups.length"
                            class="mt-4 flex flex-wrap gap-2"
                        >
                            <span
                                v-for="muscle in workout.muscle_groups.slice(
                                    0,
                                    5,
                                )"
                                :key="`${workout.id}-${muscle}`"
                                class="rounded-lg bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                            >
                                {{ muscle }}
                            </span>
                            <span
                                v-if="workout.muscle_groups.length > 5"
                                class="rounded-lg bg-gray-100 px-2 py-1 text-xs font-medium text-gray-500 dark:bg-gray-700 dark:text-gray-400"
                            >
                                +{{ workout.muscle_groups.length - 5 }}
                            </span>
                        </div>

                        <ul
                            v-if="workout.exercises.length"
                            class="mt-4 space-y-2 border-t border-gray-100 pt-4 dark:border-gray-700"
                        >
                            <li
                                v-for="exercise in workout.exercises.slice(
                                    0,
                                    3,
                                )"
                                :key="exercise.id"
                                class="flex items-center justify-between text-sm"
                            >
                                <span class="text-gray-700 dark:text-gray-300">
                                    {{ exercise.name }}
                                </span>
                                <span
                                    v-if="
                                        exercise.target_sets ||
                                        exercise.target_reps
                                    "
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                >
                                    <template v-if="exercise.target_sets">
                                        {{ exercise.target_sets }}x
                                    </template>
                                    {{ exercise.target_reps }}
                                </span>
                            </li>
                            <li
                                v-if="workout.exercises.length > 3"
                                class="text-xs text-gray-500 dark:text-gray-400"
                            >
                                +{{ workout.exercises.length - 3 }} exercícios
                            </li>
                        </ul>
                    </Link>
                </div>
            </div>
        </div>

        <Modal
            :show="showCreateModal"
            max-width="2xl"
            @close="closeCreateModal"
        >
            <form @submit.prevent="submit" class="p-6">
                <div class="mb-6">
                    <h3
                        class="text-lg font-semibold text-gray-900 dark:text-gray-100"
                    >
                        Novo Treino
                    </h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Monte sua ficha adicionando exercícios um a um.
                    </p>
                </div>

                <div class="space-y-5">
                    <div>
                        <InputLabel for="name" value="Nome do treino" />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full focus:border-violet-500 focus:ring-violet-500 dark:focus:border-violet-500 dark:focus:ring-violet-500"
                            placeholder="Ex: Treino A - Peito e Tríceps"
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div>
                        <InputLabel
                            for="description"
                            value="Descrição (opcional)"
                        />
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="2"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-violet-500 dark:focus:ring-violet-500"
                            placeholder="Observações sobre o treino..."
                        />
                        <InputError
                            class="mt-2"
                            :message="form.errors.description"
                        />
                    </div>

                    <div>
                        <div class="mb-3 flex items-center justify-between">
                            <InputLabel value="Exercícios do treino" />
                            <span
                                class="text-xs font-medium text-gray-500 dark:text-gray-400"
                            >
                                {{ form.exercises.length }} adicionado(s)
                            </span>
                        </div>

                        <div
                            v-if="form.exercises.length === 0"
                            class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center dark:border-gray-600"
                        >
                            <p
                                class="text-sm text-gray-500 dark:text-gray-400"
                            >
                                Nenhum exercício adicionado ainda.
                            </p>
                        </div>

                        <TransitionGroup
                            v-else
                            tag="ul"
                            name="list"
                            class="space-y-3"
                        >
                            <li
                                v-for="(exercise, index) in form.exercises"
                                :key="exercise.id"
                                class="rounded-xl border border-gray-200 bg-gray-50/50 p-4 dark:border-gray-700 dark:bg-gray-900/40"
                            >
                                <div
                                    class="mb-3 flex items-start justify-between gap-3"
                                >
                                    <div>
                                        <p
                                            class="text-sm font-semibold text-gray-900 dark:text-gray-100"
                                        >
                                            {{ exercise.name }}
                                        </p>
                                        <p
                                            class="text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            {{ exercise.primary_muscle }}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        @click="removeExercise(index)"
                                        class="rounded-lg p-1.5 text-gray-400 transition hover:bg-red-500/10 hover:text-red-500"
                                        title="Remover exercício"
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
                                                stroke-width="2"
                                                d="M6 18L18 6M6 6l12 12"
                                            />
                                        </svg>
                                    </button>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label
                                            class="text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            Séries
                                        </label>
                                        <input
                                            v-model.number="
                                                exercise.target_sets
                                            "
                                            type="number"
                                            min="1"
                                            max="20"
                                            placeholder="3"
                                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        />
                                    </div>
                                    <div>
                                        <label
                                            class="text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            Repetições
                                        </label>
                                        <input
                                            v-model="exercise.target_reps"
                                            type="text"
                                            placeholder="10"
                                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        />
                                    </div>
                                </div>
                            </li>
                        </TransitionGroup>

                        <button
                            type="button"
                            @click="openExerciseSelector"
                            class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-violet-500/40 px-4 py-3 text-sm font-semibold text-violet-600 transition hover:border-violet-500 hover:bg-violet-500/5 dark:text-violet-400 dark:hover:bg-violet-500/10"
                        >
                            <svg
                                class="h-5 w-5"
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
                            Adicionar Exercício
                        </button>

                        <InputError
                            class="mt-2"
                            :message="form.errors.exercises"
                        />
                    </div>
                </div>

                <div
                    class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
                >
                    <button
                        type="button"
                        @click="closeCreateModal"
                        class="rounded-xl border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center justify-center rounded-xl bg-violet-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-violet-600 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 disabled:opacity-50 dark:focus:ring-offset-gray-800"
                    >
                        {{ form.processing ? 'Salvando...' : 'Salvar treino' }}
                    </button>
                </div>
            </form>
        </Modal>

        <ExerciseSelector
            :show="showExerciseSelector"
            :catalog="exercisesCatalog"
            :selected-ids="selectedExerciseIds"
            @close="closeExerciseSelector"
            @select="addExercise"
        />
    </AuthenticatedLayout>
</template>

<style scoped>
.list-enter-active,
.list-leave-active {
    transition: all 0.25s ease;
}

.list-enter-from,
.list-leave-to {
    opacity: 0;
    transform: translateX(-12px);
}
</style>
