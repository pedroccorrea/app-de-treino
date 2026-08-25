<script setup>
import ArchivedProgramCard from '@/Components/Programs/ArchivedProgramCard.vue';
import CreateProgramModal from '@/Components/Programs/CreateProgramModal.vue';
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
    archivedPrograms: {
        type: Array,
        default: () => [],
    },
});

// ─── Programa Ativo / Programas Arquivados tabs ─────────────────────────────
const activeTab = ref('program'); // 'program' | 'archived-programs'

const activeProgramWorkouts = computed(() =>
    props.activeProgram
        ? props.workouts.filter((w) => w.workout_program_id === props.activeProgram.id)
        : props.workouts.filter((w) => !w.workout_program_id),
);

const hasWorkouts = computed(() => activeProgramWorkouts.value.length > 0);

// ─── Create program modal ───────────────────────────────────────────────────
const showCreateProgramModal = ref(false);

const programForm = useForm({
    name: '',
    description: '',
});

const openCreateProgramModal = () => {
    showCreateProgramModal.value = true;
};

const closeCreateProgramModal = () => {
    programForm.reset();
    programForm.clearErrors();
    showCreateProgramModal.value = false;
};

const submitCreateProgram = () => {
    programForm.post(route('programs.store'), {
        preserveScroll: true,
        onSuccess: () => closeCreateProgramModal(),
    });
};

// ─── Reactivate / delete archived programs ──────────────────────────────────
const reactivateProgram = (program) => {
    router.patch(route('programs.activate', program.id), {}, { preserveScroll: true });
};

const deleteProgram = (program) => {
    if (!confirm(`Tem certeza que deseja excluir o programa "${program.name}" e todas as suas fichas? Essa ação não pode ser desfeita.`)) {
        return;
    }

    router.delete(route('programs.destroy', program.id), { preserveScroll: true });
};

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
    router.delete(route('workouts.destroy', workoutPendingDeletion.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            workoutPendingDeletion.value = null;
        },
    });
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

        <CreateProgramModal
            :show="showCreateProgramModal"
            :form="programForm"
            @close="closeCreateProgramModal"
            @submit="submitCreateProgram"
        />

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <ProgramHeader
                    :active-program="activeProgram"
                    class="mb-5"
                    @create="openCreateProgramModal"
                />

                <div class="mb-5 flex gap-2">
                    <button
                        type="button"
                        @click="activeTab = 'program'"
                        :class="[
                            'rounded-xl px-4 py-2 text-sm font-semibold transition',
                            activeTab === 'program'
                                ? 'bg-violet-600 text-white shadow-sm shadow-violet-500/30'
                                : 'bg-white text-gray-600 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700',
                        ]"
                    >
                        Programa Ativo ({{ activeProgramWorkouts.length }})
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'archived-programs'"
                        :class="[
                            'rounded-xl px-4 py-2 text-sm font-semibold transition',
                            activeTab === 'archived-programs'
                                ? 'bg-violet-600 text-white shadow-sm shadow-violet-500/30'
                                : 'bg-white text-gray-600 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700',
                        ]"
                    >
                        Programas Arquivados ({{ archivedPrograms.length }})
                    </button>
                </div>

                <template v-if="activeTab === 'program'">
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
                            @open="(w) => router.visit(route('workouts.show', w.id))"
                            @toggle-archive="toggleArchive"
                            @delete="confirmDelete"
                        />
                    </div>
                </template>

                <template v-else>
                    <div
                        v-if="!archivedPrograms.length"
                        class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center dark:border-gray-700 dark:bg-gray-800"
                    >
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Nenhum programa arquivado no momento.
                        </p>
                    </div>

                    <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <ArchivedProgramCard
                            v-for="program in archivedPrograms"
                            :key="program.id"
                            :program="program"
                            @reactivate="reactivateProgram"
                            @delete="deleteProgram"
                        />
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
