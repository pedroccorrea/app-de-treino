<script setup>
import SetwaveLogo from '@/Components/Brand/SetwaveLogo.vue';
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseCard from '@/Components/UI/BaseCard.vue';
import CreateProgramModal from '@/Components/Programs/CreateProgramModal.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    programs: {
        type: Array,
        default: () => [],
    },
});

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

const activateProgram = (program) => {
    router.patch(route('programs.activate', program.id), {}, { preserveScroll: true });
};

const archiveProgram = (program) => {
    router.patch(route('programs.archive', program.id), {}, { preserveScroll: true });
};

const programPendingDeletion = ref(null);
const deleting = ref(false);

const confirmDeleteProgram = (program) => {
    programPendingDeletion.value = program;
};

const cancelDeleteProgram = () => {
    programPendingDeletion.value = null;
};

const deleteProgram = () => {
    deleting.value = true;
    router.delete(route('programs.destroy', programPendingDeletion.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            programPendingDeletion.value = null;
        },
    });
};
</script>

<template>
    <Head title="Programas de Treino" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-text-primary">
                    Programas de Treino
                </h2>

                <BaseButton variant="primary" @click="openCreateProgramModal">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    Novo Programa
                </BaseButton>
            </div>
        </template>

        <CreateProgramModal
            :show="showCreateProgramModal"
            :form="programForm"
            @close="closeCreateProgramModal"
            @submit="submitCreateProgram"
        />

        <ConfirmationModal
            :show="!!programPendingDeletion"
            title="Excluir programa?"
            :description="`Tem certeza que deseja excluir o programa '${programPendingDeletion?.name}' e todas as suas fichas? Essa ação não pode ser desfeita.`"
            confirm-text="Excluir"
            :processing="deleting"
            @cancel="cancelDeleteProgram"
            @confirm="deleteProgram"
        />

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <BaseCard v-if="!programs.length" class="border-dashed px-6 py-16 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-radius-lg bg-accent-muted">
                        <SetwaveLogo :size="32" variant="mark" />
                    </div>

                    <p class="text-sm text-text-secondary">
                        Você ainda não tem nenhum programa de treino. Crie o
                        primeiro para organizar suas fichas por ciclo.
                    </p>
                </BaseCard>

                <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <BaseCard
                        v-for="program in programs"
                        :key="program.id"
                        role="button"
                        tabindex="0"
                        @click="router.visit(route('programs.show', program.id))"
                        @keydown.enter="router.visit(route('programs.show', program.id))"
                        :class="[
                            'cursor-pointer transition hover:-translate-y-0.5 hover:border-border-accent',
                            program.is_active ? 'ring-1 ring-border-accent' : '',
                        ]"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-semibold text-text-primary">
                                    {{ program.name }}
                                </h3>
                                <p v-if="program.description" class="mt-1 text-sm text-text-secondary">
                                    {{ program.description }}
                                </p>
                                <p v-if="!program.is_active && program.archived_at" class="mt-1 text-xs text-text-tertiary">
                                    Arquivado em {{ program.archived_at }}
                                </p>
                            </div>

                            <BaseBadge v-if="program.is_active" tone="accent" class="shrink-0">
                                Ativo
                            </BaseBadge>
                        </div>

                        <p class="mt-4 text-sm text-text-secondary">
                            {{ program.workouts_count }}
                            {{ program.workouts_count === 1 ? 'ficha' : 'fichas' }}
                        </p>

                        <div class="mt-4 flex items-center gap-2 border-t border-border-subtle pt-3">
                            <BaseButton v-if="!program.is_active" variant="ghost" @click.stop="activateProgram(program)">
                                Reativar Programa
                            </BaseButton>
                            <BaseButton v-else variant="ghost" @click.stop="archiveProgram(program)">
                                Arquivar
                            </BaseButton>
                            <BaseButton variant="danger" @click.stop="confirmDeleteProgram(program)">
                                Excluir
                            </BaseButton>
                        </div>
                    </BaseCard>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
