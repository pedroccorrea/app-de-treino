<script setup>
defineProps({
    // secondary | accent — see .ai/rules/design-system.md for when to use each
    tone: {
        type: String,
        default: 'secondary',
    },
});

const toneClasses = {
    secondary: 'text-text-secondary',
    accent: 'text-accent-label',
};
</script>

<template>
    <span :class="['text-[11px] uppercase tracking-[0.2em]', toneClasses[tone]]">
        <slot />
    </span>
</template>
