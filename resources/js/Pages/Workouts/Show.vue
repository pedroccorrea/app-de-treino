<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    workout: {
        type: Object,
        required: true,
    },
});

const formatTarget = (exercise) => {
    const parts = [];

    if (exercise.target_sets) {
        parts.push(
            `${exercise.target_sets} ${
                exercise.target_sets === 1 ? 'série' : 'séries'
            }`,
        );
    }

    if (exercise.target_reps) {
        parts.push(exercise.target_reps + ' reps');
    }

    return parts.length ? parts.join(' × ') : 'Metas não definidas';
};

const hasExercises = computed(() => props.workout.exercises.length > 0);
</script>

<template>
    <Head :title="workout.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                <Link
                    :href="route('workouts.index')"
                    class="inline-flex items-center gap-1 text-sm font-medium text-gray-500 transition hover:text-violet-500 dark:text-gray-400 dark:hover:text-violet-400"
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
                            d="M15 19l-7-7 7-7"
                        />
                    </svg>
                    Voltar aos treinos
                </Link>

                <h2
                    class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                >
                    {{ workout.name }}
                </h2>
            </div>
        </template>

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <div
                    class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="border-b border-gray-100 p-6 dark:border-gray-700">
                        <p
                            v-if="workout.description"
                            class="text-sm leading-relaxed text-gray-600 dark:text-gray-400"
                        >
                            {{ workout.description }}
                        </p>
                        <p
                            v-else
                            class="text-sm italic text-gray-500 dark:text-gray-400"
                        >
                            Sem descrição para este treino.
                        </p>

                        <div
                            v-if="workout.muscle_groups.length"
                            class="mt-4 flex flex-wrap gap-2"
                        >
                            <span
                                v-for="muscle in workout.muscle_groups"
                                :key="muscle"
                                class="rounded-lg bg-violet-500/10 px-2.5 py-1 text-xs font-medium text-violet-600 dark:text-violet-400"
                            >
                                {{ muscle }}
                            </span>
                        </div>
                    </div>

                    <div class="p-6">
                        <h3
                            class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                        >
                            Exercícios ({{ workout.exercises_count }})
                        </h3>

                        <div
                            v-if="!hasExercises"
                            class="rounded-xl border border-dashed border-gray-300 px-4 py-10 text-center dark:border-gray-600"
                        >
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Este treino ainda não possui exercícios
                                cadastrados.
                            </p>
                        </div>

                        <ol v-else class="space-y-3">
                            <li
                                v-for="(exercise, index) in workout.exercises"
                                :key="exercise.id"
                                class="flex items-start gap-4 rounded-xl border border-gray-100 bg-gray-50/50 p-4 transition hover:border-violet-500/30 dark:border-gray-700 dark:bg-gray-900/40"
                            >
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-violet-500/10 text-sm font-bold text-violet-600 dark:text-violet-400"
                                >
                                    {{ index + 1 }}
                                </span>

                                <div class="min-w-0 flex-1">
                                    <p
                                        class="font-medium text-gray-900 dark:text-gray-100"
                                    >
                                        {{ exercise.name }}
                                    </p>
                                    <p
                                        class="mt-0.5 text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{ exercise.primary_muscle }}
                                    </p>
                                    <p
                                        class="mt-2 text-sm font-semibold text-violet-600 dark:text-violet-400"
                                    >
                                        {{ formatTarget(exercise) }}
                                    </p>
                                </div>
                            </li>
                        </ol>
                    </div>

                    <div
                        class="border-t border-gray-100 bg-gray-50/80 p-6 dark:border-gray-700 dark:bg-gray-900/30"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-violet-500 px-6 py-3.5 text-base font-semibold text-white shadow-lg shadow-violet-500/25 transition hover:bg-violet-600 hover:shadow-violet-500/40 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
                        >
                            🔥 Iniciar Treino
                        </button>
                        <p
                            class="mt-2 text-center text-xs text-gray-500 dark:text-gray-400"
                        >
                            Em breve: registre séries, peso e RPE em tempo real.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
