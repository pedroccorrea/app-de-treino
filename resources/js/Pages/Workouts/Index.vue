<script setup>
import SetwaveLogo from '@/Components/Brand/SetwaveLogo.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseCard from '@/Components/UI/BaseCard.vue';
import ProgramHeader from '@/Components/Programs/ProgramHeader.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import ScanWorkoutModal from '@/Components/Workouts/ScanWorkoutModal.vue';
import WorkoutCard from '@/Components/Workouts/WorkoutCard.vue';
import WorkoutsIndexHeader from '@/Components/Workouts/WorkoutsIndexHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { cacheWorkoutsList, getCachedWorkoutsList, isOffline } from '@/Utils/offlineStorage';

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

// ─── Modo offline: usa a última lista de treinos cacheada no IndexedDB ───────
// quando o dispositivo está sem conexão (ex.: a página foi reaberta a partir
// do cache do app/service worker enquanto offline). Enquanto online, cada
// carregamento desta tela grava a lista mais recente para uso futuro offline.
const offlineWorkouts = ref(null);

const displayedWorkouts = computed(() => offlineWorkouts.value ?? props.workouts);

onMounted(async () => {
    if (isOffline()) {
        offlineWorkouts.value = await getCachedWorkoutsList();
        return;
    }

    cacheWorkoutsList(props.workouts);
});

// ─── Fichas do programa ativo ────────────────────────────────────────────────
const activeProgramWorkouts = computed(() =>
    props.activeProgram
        ? displayedWorkouts.value.filter((w) => w.program_ids.includes(props.activeProgram.id))
        : displayedWorkouts.value.filter((w) => w.program_ids.length === 0),
);

const hasWorkouts = computed(() => activeProgramWorkouts.value.length > 0);

// ─── Abertura de treino (offline-first) ──────────────────────────────────────
// A navegação é sempre a mesma, online ou offline: o Cache Warming já deixou
// o JSON do Inertia para `workouts.show` no `INERTIA_CACHE` (ver
// `resources/js/Utils/cacheWarming.js`), e o Service Worker (network-first
// com fallback para esse cache) resolve a requisição de forma invisível —
// sem nenhuma tela ou painel diferente do fluxo online.
const openWorkout = (workout) => {
    router.visit(route('workouts.show', { workout: workout.id, return_to: route('workouts.index') }));
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

        <ConfirmationModal
            :show="!!workoutPendingDeletion"
            title="Excluir treino?"
            :description="`Tem certeza que deseja excluir '${workoutPendingDeletion?.name}'? Essa ação não pode ser desfeita.`"
            confirm-text="Excluir"
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

                <BaseCard
                    v-if="!hasWorkouts"
                    class="border-dashed px-6 py-16 text-center"
                >
                    <div
                        class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-radius-lg bg-accent-muted"
                    >
                        <SetwaveLogo :size="32" variant="mark" />
                    </div>

                    <h3 class="text-lg font-semibold text-text-primary">
                        Você ainda não tem treinos cadastrados
                    </h3>
                    <p class="mx-auto mt-2 max-w-md text-sm text-text-secondary">
                        Crie sua primeira ficha de treino selecionando
                        exercícios do catálogo e organize sua rotina semanal.
                    </p>

                    <BaseButton variant="primary" class="mx-auto mt-6" @click="router.visit(route('workouts.create'))">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2.5"
                                d="M12 4v16m8-8H4"
                            />
                        </svg>
                        Criar meu primeiro treino
                    </BaseButton>
                </BaseCard>

                <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <WorkoutCard
                        v-for="workout in activeProgramWorkouts"
                        :key="workout.id"
                        :workout="workout"
                        :current-path="currentPath"
                        @open="openWorkout"
                        @toggle-archive="toggleArchive"
                        @delete="confirmDelete"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
