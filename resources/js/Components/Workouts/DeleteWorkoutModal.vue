<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

defineProps({
    workout: {
        type: Object,
        default: null,
    },
    processing: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['confirm', 'cancel']);
</script>

<template>
    <Modal :show="!!workout" @close="emit('cancel')">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Excluir treino?
            </h2>

            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Tem certeza que deseja excluir
                <strong>{{ workout?.name }}</strong>? Essa ação não pode ser
                desfeita.
            </p>

            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton @click="emit('cancel')">
                    Cancelar
                </SecondaryButton>

                <DangerButton
                    :class="{ 'opacity-25': processing }"
                    :disabled="processing"
                    @click="emit('confirm')"
                >
                    Excluir
                </DangerButton>
            </div>
        </div>
    </Modal>
</template>
