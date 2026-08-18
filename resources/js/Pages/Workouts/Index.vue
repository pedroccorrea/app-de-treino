<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    workouts: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const hasWorkouts = computed(() => props.workouts.length > 0);
</script>

<template>
    <Head title="Treinos" />

    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <h2
                    class="text-xl font-bold leading-tight text-gray-900 dark:text-gray-100"
                >
                    Treinos
                </h2>

                <Link
                    :href="route('workouts.create')"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-violet-500/20 transition hover:bg-violet-700 hover:shadow-violet-500/30 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
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
                            stroke-width="2.5"
                            d="M12 4v16m8-8H4"
                        />
                    </svg>
                    Novo Treino
                </Link>
            </div>
        </template>

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div
                    v-if="page.props.flash?.success"
                    class="mb-6 rounded-2xl border border-violet-500/30 bg-violet-500/10 px-4 py-3.5 text-sm font-medium text-violet-700 dark:text-violet-300"
                >
                    {{ page.props.flash.success }}
                </div>

                <div
                    v-if="!hasWorkouts"
                    class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center dark:border-gray-700 dark:bg-gray-800"
                >
                    <div
                        class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-violet-500/10 text-3xl text-violet-600 dark:text-violet-400"
                    >
                        🏋️‍♂️
                    </div>

                    <h3
                        class="text-lg font-bold text-gray-900 dark:text-gray-100"
                    >
                        Você ainda não tem treinos cadastrados
                    </h3>
                    <p
                        class="mx-auto mt-2 max-w-md text-sm text-gray-600 dark:text-gray-400"
                    >
                        Crie sua primeira ficha de treino selecionando
                        exercícios do catálogo e organize sua rotina semanal.
                    </p>

                    <Link
                        :href="route('workouts.create')"
                        class="mt-6 inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-violet-500/20 transition hover:bg-violet-700"
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
                                stroke-width="2.5"
                                d="M12 4v16m8-8H4"
                            />
                        </svg>
                        Criar meu primeiro treino
                    </Link>
                </div>

                <div
                    v-else
                    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <Link
                        v-for="workout in workouts"
                        :key="workout.id"
                        :href="route('workouts.show', workout.id)"
                        class="group block rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition duration-150 hover:-translate-y-0.5 hover:border-violet-500/40 hover:shadow-md dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3
                                    class="text-lg font-bold text-gray-900 transition group-hover:text-violet-600 dark:text-gray-100 dark:group-hover:text-violet-400"
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
                                class="inline-flex shrink-0 items-center rounded-full bg-violet-500/10 px-2.5 py-1 text-xs font-bold text-violet-600 dark:text-violet-400"
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
                            class="mt-4 flex flex-wrap gap-1.5"
                        >
                            <span
                                v-for="muscle in workout.muscle_groups.slice(
                                    0,
                                    5,
                                )"
                                :key="`${workout.id}-${muscle}`"
                                class="rounded-lg bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                            >
                                {{ muscle }}
                            </span>
                            <span
                                v-if="workout.muscle_groups.length > 5"
                                class="rounded-lg bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-700 dark:text-gray-400"
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
                                <span class="text-gray-700 dark:text-gray-300 font-medium">
                                    {{ exercise.name }}
                                </span>
                                <span
                                    v-if="
                                        exercise.target_sets ||
                                        exercise.target_reps
                                    "
                                    class="text-xs font-semibold text-gray-500 dark:text-gray-400"
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
    </AuthenticatedLayout>
</template>
