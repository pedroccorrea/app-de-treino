<script setup>
import BaseButton from '@/Components/UI/BaseButton.vue';

defineProps({
    saving: {
        type: Boolean,
        default: false,
    },
    label: {
        type: String,
        default: 'Concluir série',
    },
});

defineEmits(['click']);
</script>

<template>
    <BaseButton
        variant="primary"
        size="hero"
        ripple
        :disabled="saving"
        class="w-full"
        @click="$emit('click')"
    >
        <template v-if="saving">
            <svg class="h-5 w-5 animate-spin text-accent-text-soft" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <span class="text-lg font-medium tracking-[0.01em]">Salvando...</span>
        </template>
        <template v-else>
            <span class="text-lg font-medium tracking-[0.01em]">{{ label }}</span>
            <svg width="26" height="10" viewBox="0 0 26 10" fill="none">
                <path
                    d="M1 5C3.5 1 6 1 8.5 5S13.5 9 16 5s5-4 7.5 0"
                    stroke="var(--accent-text-soft)"
                    stroke-width="1.5"
                    stroke-linecap="round"
                />
            </svg>
        </template>
    </BaseButton>
</template>
