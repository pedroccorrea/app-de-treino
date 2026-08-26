<script setup>
import ProgramHeader from '@/Components/Programs/ProgramHeader.vue';
import DeleteWorkoutModal from '@/Components/Workouts/DeleteWorkoutModal.vue';
import ScanWorkoutModal from '@/Components/Workouts/ScanWorkoutModal.vue';
import WorkoutCard from '@/Components/Workouts/WorkoutCard.vue';
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
    activeProgram: {
        type: Object,
        default: null,
    },
});

// ─── Fichas do programa ativo ────────────────────────────────────────────────
const activeProgramWorkouts = computed(() =>
    props.activeProgram
        ? props.workouts.filter((w) => w.workout_program_id === props.activeProgram.id)
        : props.workouts.filter((w) => !w.workout_program_id),
);

const hasWorkouts = computed(() => activeProgramWorkouts.value.length > 0);

// ─── Archive / reactivate ───────────────────────────────────────────────────
const toggleArchive = (workout) => {
    router.patch(route('workouts.archive', workout.id), {}, { preserveScroll: true });
};

// ─── Delete with confirmation ───────────────────────────────────────────────
const workoutPendingDeletion = ref(null);
const deleting = ref(false);

const confirmDelete = (workout) => {
    workoutPendingDeletion.value = workout;
};

const cancelDelete = () => {
    workoutPendingDeletion.value = null;
};

const deleteWorkout = () => {
    deleting.value = true;
    router.delete(
        route('workouts.destroy', { workout: workoutPendingDeletion.value.id, return_to: route('workouts.index') }),
        {
            preserveScroll: true,
            onFinish: () => {
                deleting.value = false;
                workoutPendingDeletion.value = null;
            },
        },
    );
};

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

        <DeleteWorkoutModal
            :workout="workoutPendingDeletion"
            :processing="deleting"
            @cancel="cancelDelete"
            @confirm="deleteWorkout"
        />

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <ProgramHeader
                    :active-program="activeProgram"
                    class="mb-5"
                />

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

                <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <WorkoutCard
                        v-for="workout in activeProgramWorkouts"
                        :key="workout.id"
                        :workout="workout"
                        :current-path="currentPath"
                        @open="(w) => router.visit(route('workouts.show', { workout: w.id, return_to: route('workouts.index') }))"
                        @toggle-archive="toggleArchive"
                        @delete="confirmDelete"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
