<script setup>
import CreateProgramModal from '@/Components/Programs/CreateProgramModal.vue';
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

const deleteProgram = (program) => {
    if (!confirm(`Tem certeza que deseja excluir o programa "${program.name}" e todas as suas fichas? Essa ação não pode ser desfeita.`)) {
        return;
    }

    router.delete(route('programs.destroy', program.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Programas de Treino" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                    Programas de Treino
                </h2>

                <button
                    type="button"
                    @click="openCreateProgramModal"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-violet-500/20 transition hover:bg-violet-700"
                >
                    + Novo Programa
                </button>
            </div>
        </template>

        <CreateProgramModal
            :show="showCreateProgramModal"
            :form="programForm"
            @close="closeCreateProgramModal"
            @submit="submitCreateProgram"
        />

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div
                    v-if="!programs.length"
                    class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center dark:border-gray-700 dark:bg-gray-800"
                >
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Você ainda não tem nenhum programa de treino. Crie o
                        primeiro para organizar suas fichas por ciclo.
                    </p>
                </div>

                <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="program in programs"
                        :key="program.id"
                        @click="router.visit(route('programs.show', program.id))"
                        :class="[
                            'cursor-pointer rounded-2xl border bg-white p-5 shadow-sm transition duration-150 hover:-translate-y-0.5 hover:shadow-md dark:bg-gray-800',
                            program.is_active
                                ? 'border-violet-400/60 ring-1 ring-violet-400/40 dark:border-violet-500/50'
                                : 'border-gray-200 hover:border-violet-500/40 dark:border-gray-700',
                        ]"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                                    {{ program.name }}
                                </h3>
                                <p v-if="program.description" class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    {{ program.description }}
                                </p>
                                <p v-if="!program.is_active && program.archived_at" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Arquivado em {{ program.archived_at }}
                                </p>
                            </div>

                            <span
                                v-if="program.is_active"
                                class="inline-flex shrink-0 items-center rounded-full bg-violet-500/10 px-2.5 py-1 text-xs font-bold text-violet-600 dark:text-violet-400"
                            >
                                Ativo
                            </span>
                        </div>

                        <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                            {{ program.workouts_count }}
                            {{ program.workouts_count === 1 ? 'ficha' : 'fichas' }}
                        </p>

                        <div class="mt-4 flex items-center gap-2 border-t border-gray-100 pt-3 dark:border-gray-700">
                            <button
                                v-if="!program.is_active"
                                type="button"
                                @click.stop="activateProgram(program)"
                                class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-violet-600 transition hover:bg-violet-50 dark:text-violet-400 dark:hover:bg-violet-500/10"
                            >
                                ⚡ Reativar Programa
                            </button>
                            <button
                                v-else
                                type="button"
                                @click.stop="archiveProgram(program)"
                                class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                            >
                                📦 Arquivar
                            </button>
                            <button
                                type="button"
                                @click.stop="deleteProgram(program)"
                                class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-red-500 transition hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/10"
                            >
                                🗑️ Excluir
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
