<script setup>
import Modal from '@/Components/Modal.vue';
import SetEditor from '@/Components/Sets/SetEditor.vue';
import SectionLabel from '@/Components/UI/SectionLabel.vue';

// Modal de correção de uma série do histórico: só a "moldura" (título,
// aviso de conexão) em volta do editor reutilizável `Sets/SetEditor.vue`.
defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    // { id, set_number, weight, reps } ou null quando fechado.
    set: {
        type: Object,
        default: null,
    },
    exerciseName: {
        type: String,
        default: '',
    },
    equipment: {
        type: String,
        default: null,
    },
    processing: {
        type: Boolean,
        default: false,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    // Aviso amigável (ex: sem conexão). Nada é perdido: o rascunho continua
    // aberto para tentar de novo.
    notice: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['save', 'close']);
</script>

<template>
    <Modal :show="show" max-width="md" @close="emit('close')">
        <div v-if="set" class="bg-surface-overlay p-5 sm:p-6">
            <SectionLabel tone="accent">Editar série {{ set.set_number }}</SectionLabel>
            <h2 class="mt-1 text-lg font-semibold text-text-primary">{{ exerciseName }}</h2>

            <p
                v-if="notice"
                role="alert"
                class="mt-3 rounded-radius-sm border border-warning px-3 py-2 text-sm text-warning"
            >
                {{ notice }}
            </p>

            <SetEditor
                class="mt-5"
                :weight="set.weight"
                :reps="set.reps"
                :equipment="equipment"
                :processing="processing"
                :errors="errors"
                @save="emit('save', $event)"
                @cancel="emit('close')"
            />
        </div>
    </Modal>
</template>
