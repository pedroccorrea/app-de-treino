<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { computed } from 'vue';

const props = defineProps({
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
    // Danger actions (delete/detach) render with DangerButton; otherwise a
    // regular PrimaryButton is used for the confirm action.
    danger: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['confirm', 'cancel']);

const ConfirmButton = computed(() => (props.danger ? DangerButton : PrimaryButton));
</script>

<template>
    <Modal :show="show" @close="emit('cancel')">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ title }}
            </h2>

            <p v-if="description" class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                {{ description }}
            </p>

            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton :disabled="processing" @click="emit('cancel')">
                    {{ cancelText }}
                </SecondaryButton>

                <component
                    :is="ConfirmButton"
                    :class="{ 'opacity-25': processing }"
                    :disabled="processing"
                    @click="emit('confirm')"
                >
                    {{ processing ? 'Processando...' : confirmText }}
                </component>
            </div>
        </div>
    </Modal>
</template>
