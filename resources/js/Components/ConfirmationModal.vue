<script setup>
import BaseButton from '@/Components/UI/BaseButton.vue';
import Modal from '@/Components/Modal.vue';

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
    confirmText: {
        type: String,
        default: 'Confirmar',
    },
    cancelText: {
        type: String,
        default: 'Cancelar',
    },
    processing: {
        type: Boolean,
        default: false,
    },
    // Danger actions (delete/detach) render the confirm button with
    // BaseButton variant="danger"; otherwise variant="primary" is used.
    danger: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['confirm', 'cancel']);
</script>

<template>
    <Modal :show="show" @close="emit('cancel')">
        <div class="bg-surface-overlay p-6">
            <h2 class="text-lg font-semibold text-text-primary">
                {{ title }}
            </h2>

            <p v-if="description" class="mt-2 text-sm text-text-secondary">
                {{ description }}
            </p>

            <div class="mt-6 flex justify-end gap-3">
                <BaseButton variant="secondary" :disabled="processing" @click="emit('cancel')">
                    {{ cancelText }}
                </BaseButton>

                <BaseButton
                    :variant="danger ? 'danger' : 'primary'"
                    :disabled="processing"
                    @click="emit('confirm')"
                >
                    {{ processing ? 'Processando...' : confirmText }}
                </BaseButton>
            </div>
        </div>
    </Modal>
</template>
