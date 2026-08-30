<script setup>
import SetwaveLogo from '@/Components/Brand/SetwaveLogo.vue';
import AttachWorkoutsModal from '@/Components/Programs/AttachWorkoutsModal.vue';
import ProgramWorkoutCard from '@/Components/Programs/ProgramWorkoutCard.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import CreateWorkoutModal from '@/Components/Workouts/CreateWorkoutModal.vue';
import ScanWorkoutModal from '@/Components/Workouts/ScanWorkoutModal.vue';
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseCard from '@/Components/UI/BaseCard.vue';
import SectionLabel from '@/Components/UI/SectionLabel.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    program: {
        type: Object,
        required: true,
    },
    availableWorkouts: {
        type: Array,
        default: () => [],
    },
});

// ─── Inline edit do cabeçalho (nome/descrição) ──────────────────────────────
const isEditing = ref(false);

const editForm = useForm({
    name: props.program.name,
    description: props.program.description,
});

const startEditing = () => {
    editForm.name = props.program.name;
    editForm.description = props.program.description;
    editForm.clearErrors();
    isEditing.value = true;
};

const cancelEditing = () => {
    editForm.clearErrors();
    isEditing.value = false;
};

const saveProgram = () => {
    editForm.put(route('programs.update', props.program.id), {
        preserveScroll: true,
        onSuccess: () => {
            isEditing.value = false;
        },
    });
};

// ─── Navegar para visualização da ficha (card) ou edição (ícone de lápis) ───
const returnToProgram = route('programs.show', props.program.id);

// ─── Seletor de treinos existentes (múltiplos, via checkboxes) ──────────────
const showAttachWorkoutsModal = ref(false);
const attaching = ref(false);

const openAttachWorkoutsModal = () => {
    showAttachWorkoutsModal.value = true;
};

const closeAttachWorkoutsModal = () => {
    showAttachWorkoutsModal.value = false;
};

const attachWorkouts = (workoutIds) => {
    attaching.value = true;
    router.post(
        route('programs.workouts.attach', props.program.id),
        { workout_ids: workoutIds },
        {
            preserveScroll: true,
            onFinish: () => {
                attaching.value = false;
                showAttachWorkoutsModal.value = false;
            },
        },
    );
};

// ─── Modal de criação de ficha, já associada a este programa ───────────────
const showCreateWorkoutModal = ref(false);

const workoutForm = useForm({
    name: '',
    description: '',
    days_of_week: [],
    workout_program_id: props.program.id,
    return_to: returnToProgram,
});

const openCreateWorkoutModal = () => {
    showAttachWorkoutsModal.value = false;
    showCreateWorkoutModal.value = true;
};

const closeCreateWorkoutModal = () => {
    workoutForm.reset();
    workoutForm.clearErrors();
    showCreateWorkoutModal.value = false;
};

const submitCreateWorkout = () => {
    workoutForm.post(route('workouts.store'), {
        preserveScroll: true,
        onSuccess: () => closeCreateWorkoutModal(),
    });
};

// ─── Modal de escaneamento de ficha, já associada a este programa ──────────
const showScanModal = ref(false);
const scanModal = ref(null);

const scanForm = useForm({
    image: null,
    workout_program_id: props.program.id,
});

const openScanModal = () => {
    showAttachWorkoutsModal.value = false;
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

const openWorkout = (workout) => {
    router.visit(route('workouts.show', { workout: workout.id, return_to: returnToProgram }));
};

// ─── Desvincular ficha do programa (não exclui a ficha) com confirmação ────
const workoutPendingDetach = ref(null);
const detaching = ref(false);

const confirmDetach = (workout) => {
    workoutPendingDetach.value = workout;
};

const cancelDetach = () => {
    workoutPendingDetach.value = null;
};

const detachWorkout = () => {
    detaching.value = true;
    router.delete(
        route('programs.workouts.detach', { program: props.program.id, workout: workoutPendingDetach.value.id }),
        {
            preserveScroll: true,
            onFinish: () => {
                detaching.value = false;
                workoutPendingDetach.value = null;
            },
        },
    );
};
</script>

<template>
    <Head :title="program.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('programs.index')"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-text-secondary transition hover:text-accent-text-soft"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Voltar aos programas
                </Link>
            </div>
        </template>

        <AttachWorkoutsModal
            :show="showAttachWorkoutsModal"
            :available-workouts="availableWorkouts"
            :processing="attaching"
            @close="closeAttachWorkoutsModal"
            @submit="attachWorkouts"
            @create-new="openCreateWorkoutModal"
            @scan="openScanModal"
        />

        <CreateWorkoutModal
            :show="showCreateWorkoutModal"
            :form="workoutForm"
            @close="closeCreateWorkoutModal"
            @submit="submitCreateWorkout"
        />

        <ScanWorkoutModal
            ref="scanModal"
            :show="showScanModal"
            :form="scanForm"
            @close="closeScanModal"
            @submit="submitScan"
        />

        <ConfirmationModal
            :show="!!workoutPendingDetach"
            title="Desvincular treino?"
            :description="`Tem certeza que deseja desvincular '${workoutPendingDetach?.name}' deste programa? A ficha não será excluída e continua disponível para ser vinculada novamente.`"
            confirm-text="Desvincular"
            :processing="detaching"
            @cancel="cancelDetach"
            @confirm="detachWorkout"
        />

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <!-- Cabeçalho do programa: visualização / edição inline -->
                <BaseCard>
                    <div v-if="!isEditing" class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h2 class="truncate text-xl font-semibold text-text-primary">
                                    {{ program.name }}
                                </h2>
                                <BaseBadge v-if="program.is_active" tone="accent">
                                    Ativo
                                </BaseBadge>
                            </div>
                            <p v-if="program.description" class="mt-1 text-sm text-text-secondary">
                                {{ program.description }}
                            </p>
                        </div>

                        <button
                            type="button"
                            @click="startEditing"
                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-radius-sm text-text-secondary transition hover:bg-accent-muted hover:text-accent-text-soft"
                            title="Editar programa"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </button>
                    </div>

                    <form v-else @submit.prevent="saveProgram" class="space-y-4">
                        <div>
                            <InputLabel for="program_name" value="Nome do programa" />
                            <TextInput
                                id="program_name"
                                v-model="editForm.name"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="editForm.errors.name" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="program_description" value="Descrição (opcional)" />
                            <TextInput
                                id="program_description"
                                v-model="editForm.description"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="editForm.errors.description" class="mt-2" />
                        </div>

                        <div class="flex justify-end gap-3">
                            <BaseButton type="button" variant="secondary" :disabled="editForm.processing" @click="cancelEditing">
                                Cancelar
                            </BaseButton>
                            <BaseButton type="submit" variant="primary" :disabled="editForm.processing">
                                {{ editForm.processing ? 'Salvando...' : 'Salvar' }}
                            </BaseButton>
                        </div>
                    </form>
                </BaseCard>

                <!-- Fichas vinculadas -->
                <div class="mt-6">
                    <div class="mb-4 flex items-center justify-between">
                        <SectionLabel tone="secondary">Fichas deste programa</SectionLabel>
                        <BaseButton variant="primary" @click="openAttachWorkoutsModal">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                            </svg>
                            Adicionar Treino
                        </BaseButton>
                    </div>

                    <BaseCard v-if="!program.workouts.length" class="border-dashed px-6 py-16 text-center">
                        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-radius-lg bg-accent-muted">
                            <SetwaveLogo :size="32" variant="mark" />
                        </div>

                        <p class="text-sm text-text-secondary">
                            Nenhuma ficha vinculada a este programa ainda.
                        </p>
                    </BaseCard>

                    <div v-else class="space-y-3">
                        <ProgramWorkoutCard
                            v-for="workout in program.workouts"
                            :key="workout.id"
                            :workout="workout"
                            :return-to="returnToProgram"
                            @open="openWorkout"
                            @delete="confirmDetach"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
