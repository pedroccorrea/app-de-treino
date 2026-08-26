<script setup>
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { ref, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    availableWorkouts: {
        type: Array,
        default: () => [],
    },
    processing: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'submit', 'create-new', 'scan']);

const selectedIds = ref([]);

// Selection is scoped to a single "open" of the modal.
watch(
    () => props.show,
    (show) => {
        if (show) {
            selectedIds.value = [];
        }
    },
);

const toggle = (workoutId) => {
    const index = selectedIds.value.indexOf(workoutId);

    if (index === -1) {
        selectedIds.value.push(workoutId);
    } else {
        selectedIds.value.splice(index, 1);
    }
};

const submit = () => {
    emit('submit', [...selectedIds.value]);
};
</script>

<template>
    <Modal :show="show" @close="emit('close')">
        <div class="p-6">
            <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                Adicionar Treino ao Programa
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Selecione fichas já existentes ou crie/escaneie uma nova
                diretamente para este programa.
            </p>

            <div class="mt-4 grid grid-cols-2 gap-3">
                <button
                    type="button"
                    class="flex flex-col items-center justify-center gap-1 rounded-xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm font-semibold text-violet-700 transition hover:bg-violet-100 dark:border-violet-500/20 dark:bg-violet-500/10 dark:text-violet-400 dark:hover:bg-violet-500/20"
                    @click="emit('create-new')"
                >
                    <span class="text-xl">+</span>
                    Criar Novo Treino
                </button>

                <button
                    type="button"
                    class="flex flex-col items-center justify-center gap-1 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-700 transition hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-700/40 dark:text-gray-300 dark:hover:bg-gray-700"
                    @click="emit('scan')"
                >
                    <span class="text-xl">📷</span>
                    Escanear Ficha
                </button>
            </div>

            <div class="mt-5">
                <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Fichas existentes
                </h3>

                <p
                    v-if="!availableWorkouts.length"
                    class="mt-2 text-sm text-gray-600 dark:text-gray-400"
                >
                    Todos os seus treinos já estão vinculados a este
                    programa.
                </p>

                <ul v-else class="mt-2 max-h-64 space-y-2 overflow-y-auto">
                    <li v-for="workout in availableWorkouts" :key="workout.id">
                        <label
                            class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 p-3 transition hover:border-violet-400/60 dark:border-gray-700"
                        >
                            <input
                                type="checkbox"
                                :checked="selectedIds.includes(workout.id)"
                                class="h-4 w-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500"
                                @change="toggle(workout.id)"
                            />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {{ workout.name }}
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ workout.exercises_count }}
                                    {{ workout.exercises_count === 1 ? 'exercício' : 'exercícios' }}
                                </span>
                            </span>
                        </label>
                    </li>
                </ul>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton :disabled="processing" @click="emit('close')">
                    Cancelar
                </SecondaryButton>
                <PrimaryButton
                    :disabled="processing || !selectedIds.length"
                    :class="{ 'opacity-50': processing || !selectedIds.length }"
                    @click="submit"
                >
                    {{ processing ? 'Vinculando...' : 'Vincular Selecionados' }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
