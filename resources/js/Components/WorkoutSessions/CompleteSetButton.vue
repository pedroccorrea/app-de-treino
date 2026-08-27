<script setup>
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
    <button
        type="button"
        :disabled="saving"
        @click="$emit('click')"
        class="relative flex h-[68px] w-full items-center justify-center gap-3 overflow-hidden rounded-[20px] border border-[#8B5CF6] bg-[#8B5CF6]/10 shadow-[0_0_40px_rgba(139,92,246,0.14)] transition hover:bg-[#8B5CF6]/[.18] active:bg-[#8B5CF6]/[.26] disabled:opacity-70"
    >
        <template v-if="saving">
            <svg class="h-5 w-5 animate-spin text-[#C4B5FD]" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <span class="text-lg font-medium tracking-[0.01em] text-[#EDE9FE]">Salvando...</span>
        </template>
        <template v-else>
            <span class="relative text-lg font-medium tracking-[0.01em] text-[#EDE9FE]">{{ label }}</span>
            <svg width="26" height="10" viewBox="0 0 26 10" fill="none" class="relative">
                <path
                    d="M1 5C3.5 1 6 1 8.5 5S13.5 9 16 5s5-4 7.5 0"
                    stroke="#C4B5FD"
                    stroke-width="1.5"
                    stroke-linecap="round"
                />
            </svg>
        </template>
    </button>
</template>
