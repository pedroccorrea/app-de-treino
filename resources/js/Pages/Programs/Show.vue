<script setup>
import CreateWorkoutModal from '@/Components/Workouts/CreateWorkoutModal.vue';
import DeleteWorkoutModal from '@/Components/Workouts/DeleteWorkoutModal.vue';
import ProgramWorkoutCard from '@/Components/Programs/ProgramWorkoutCard.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    program: {
        type: Object,
        required: true,
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

// ─── Modal de criação de ficha, já associada a este programa ───────────────
const showCreateWorkoutModal = ref(false);

const workoutForm = useForm({
    name: '',
    description: '',
    days_of_week: [],
    workout_program_id: props.program.id,
});

const openCreateWorkoutModal = () => {
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

// ─── Navegar para edição da ficha ────────────────────────────────────────────
const currentPath = window.location.pathname + window.location.search;

const openWorkout = (workout) => {
    router.visit(route('workouts.edit', { workout: workout.id, redirect_to: currentPath }));
};

// ─── Exclusão de ficha com confirmação ──────────────────────────────────────
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
</script>

<template>
    <Head :title="program.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('programs.index')"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 transition hover:text-violet-600 dark:text-gray-400 dark:hover:text-violet-400"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Voltar aos programas
                </Link>
            </div>
        </template>

        <CreateWorkoutModal
            :show="showCreateWorkoutModal"
            :form="workoutForm"
            @close="closeCreateWorkoutModal"
            @submit="submitCreateWorkout"
        />

        <DeleteWorkoutModal
            :workout="workoutPendingDeletion"
            :processing="deleting"
            @cancel="cancelDelete"
            @confirm="deleteWorkout"
        />

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <!-- Cabeçalho do programa: visualização / edição inline -->
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div v-if="!isEditing" class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h2 class="truncate text-xl font-bold text-gray-900 dark:text-gray-100">
                                    {{ program.name }}
                                </h2>
                                <span
                                    v-if="program.is_active"
                                    class="inline-flex shrink-0 items-center rounded-full bg-violet-500/10 px-2.5 py-1 text-xs font-bold text-violet-600 dark:text-violet-400"
                                >
                                    Ativo
                                </span>
                            </div>
                            <p v-if="program.description" class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                {{ program.description }}
                            </p>
                        </div>

                        <button
                            type="button"
                            @click="startEditing"
                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-gray-400 transition hover:bg-violet-500/10 hover:text-violet-600 dark:hover:text-violet-400"
                            title="Editar programa"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                autofocus
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
                            <SecondaryButton type="button" :disabled="editForm.processing" @click="cancelEditing">
                                Cancelar
                            </SecondaryButton>
                            <PrimaryButton
                                type="submit"
                                :disabled="editForm.processing"
                                :class="{ 'opacity-50': editForm.processing }"
                            >
                                {{ editForm.processing ? 'Salvando...' : 'Salvar' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

                <!-- Fichas vinculadas -->
                <div class="mt-6">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">
                            Fichas deste programa
                        </h3>
                        <button
                            type="button"
                            @click="openCreateWorkoutModal"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white shadow-md shadow-violet-500/20 transition hover:bg-violet-700"
                        >
                            + Adicionar Ficha a este Programa
                        </button>
                    </div>

                    <div
                        v-if="!program.workouts.length"
                        class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center dark:border-gray-700 dark:bg-gray-800"
                    >
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Nenhuma ficha vinculada a este programa ainda.
                        </p>
                    </div>

                    <div v-else class="space-y-3">
                        <ProgramWorkoutCard
                            v-for="workout in program.workouts"
                            :key="workout.id"
                            :workout="workout"
                            @open="openWorkout"
                            @delete="confirmDelete"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
