<script setup>
import ScanWorkoutModal from '@/Components/Workouts/ScanWorkoutModal.vue';
import WorkoutsIndexHeader from '@/Components/Workouts/WorkoutsIndexHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    workouts: {
        type: Array,
        default: () => [],
    },
    todayDayOfWeek: {
        type: Number,
        default: null,
    },
    todayDayOfWeekLabel: {
        type: String,
        default: '',
    },
});

const hasWorkouts = computed(() => props.workouts.length > 0);

// Where the edit link should send the user back to after saving.
const currentPath = window.location.pathname + window.location.search;

const showScanModal = ref(false);
const scanModal = ref(null);

const scanForm = useForm({
    image: null,
});

const openScanModal = () => {
    showScanModal.value = true;
};

const closeScanModal = () => {
    scanForm.reset();
    scanForm.clearErrors();
    scanModal.value?.resetPreview();
    showScanModal.value = false;
};

const submitScan = () => {
    scanForm.post(route('workouts.scan'), {
        forceFormData: true,
        onSuccess: () => closeScanModal(),
    });
};
</script>

<template>
    <Head title="Treinos" />

    <AuthenticatedLayout>
        <template #header>
            <WorkoutsIndexHeader
                :today-day-of-week-label="todayDayOfWeekLabel"
                @scan="openScanModal"
            />
        </template>

        <ScanWorkoutModal
            ref="scanModal"
            :show="showScanModal"
            :form="scanForm"
            @close="closeScanModal"
            @submit="submitScan"
        />

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
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
                    <div
                        v-for="workout in workouts"
                        :key="workout.id"
                        @click="router.visit(route('workouts.show', workout.id))"
                        :class="[
                            'group block cursor-pointer rounded-2xl border bg-white p-5 shadow-sm transition duration-150 hover:-translate-y-0.5 hover:shadow-md dark:bg-gray-800',
                            workout.is_today
                                ? 'border-orange-400/60 ring-1 ring-orange-400/40 dark:border-orange-500/50'
                                : 'border-gray-200 hover:border-violet-500/40 dark:border-gray-700',
                        ]"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-1.5">
                                    <h3
                                        class="text-lg font-bold text-gray-900 transition group-hover:text-violet-600 dark:text-gray-100 dark:group-hover:text-violet-400"
                                    >
                                        {{ workout.name }}
                                    </h3>
                                    <Link
                                        :href="route('workouts.edit', { workout: workout.id, redirect_to: currentPath })"
                                        @click.stop
                                        class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-lg text-gray-400 transition hover:bg-violet-500/10 hover:text-violet-600 dark:hover:text-violet-400"
                                        title="Editar treino"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </Link>
                                </div>
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
                            v-if="workout.is_today || workout.days_of_week_labels.length"
                            class="mt-3 flex flex-wrap items-center gap-1.5"
                        >
                            <span
                                v-if="workout.is_today"
                                class="inline-flex items-center gap-1 rounded-full bg-gradient-to-r from-orange-500 to-red-500 px-2.5 py-1 text-xs font-bold text-white shadow-md shadow-orange-500/30 animate-pulse"
                            >
                                🔥 Treino de Hoje
                            </span>
                            <span
                                v-if="workout.days_of_week_labels.length"
                                class="inline-flex items-center gap-1 rounded-lg bg-violet-500/10 px-2.5 py-1 text-xs font-semibold text-violet-600 dark:text-violet-400"
                            >
                                📅 {{ workout.days_of_week_labels.join(', ') }}
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
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
