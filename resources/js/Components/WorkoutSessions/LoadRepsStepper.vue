<script setup>
defineProps({
    label: {
        type: String,
        required: true,
    },
});

defineEmits(['decrement', 'increment']);
</script>

<template>
    <div class="flex flex-1 flex-col gap-2">
        <div class="flex gap-2">
            <button
                type="button"
                aria-label="Diminuir"
                @click="$emit('decrement')"
                class="flex h-14 flex-1 items-center justify-center rounded-radius-md border border-white/[.07] bg-surface-raised transition hover:border-accent/[.35] hover:bg-surface-overlay active:bg-surface-overlay"
            >
                <span class="h-[1.8px] w-4 rounded-sm bg-text-numeric"></span>
            </button>
            <button
                type="button"
                aria-label="Aumentar"
                @click="$emit('increment')"
                class="relative flex h-14 flex-1 items-center justify-center rounded-radius-md border border-white/[.07] bg-surface-raised transition hover:border-accent/[.35] hover:bg-surface-overlay active:bg-surface-overlay"
            >
                <span class="relative h-[1.8px] w-4 rounded-sm bg-text-numeric">
                    <span class="absolute left-[7.1px] top-[-7.1px] h-4 w-[1.8px] rounded-sm bg-text-numeric"></span>
                </span>
            </button>
        </div>
        <span class="text-center text-[10px] uppercase tracking-[0.18em] text-text-tertiary">{{ label }}</span>
    </div>
</template>
