<script setup>
import { computed } from 'vue';

const props = defineProps({
    size: {
        type: [Number, String],
        default: 32,
    },
    variant: {
        type: String,
        default: 'full',
        validator: (value) => ['full', 'mark'].includes(value),
    },
});

const pixelSize = computed(() => (typeof props.size === 'number' ? `${props.size}px` : props.size));
</script>

<template>
    <span class="inline-flex items-center gap-2 text-accent">
        <svg
            :width="pixelSize"
            :height="pixelSize"
            viewBox="0 0 512 512"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            role="img"
            aria-label="Setwave"
        >
            <path
                d="M64 336c53.33 0 53.33-160 106.67-160S224 336 277.33 336 330.67 176 384 176s53.33 160 64 160"
                stroke="currentColor"
                stroke-width="40"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
        </svg>
        <span
            v-if="variant === 'full'"
            class="text-lg font-bold text-text-primary"
        >
            Setwave
        </span>
    </span>
</template>
