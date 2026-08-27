<script setup>
defineProps({
    // accent | success | danger | warning | neutral
    tone: {
        type: String,
        default: 'accent',
    },
});

const toneClasses = {
    accent: 'border-border-accent bg-accent-muted text-accent-text-soft',
    success: 'border-success bg-transparent text-success',
    danger: 'border-danger bg-transparent text-danger',
    warning: 'border-warning bg-transparent text-warning',
    neutral: 'border-border-subtle bg-transparent text-text-secondary',
};
</script>

<template>
    <span
        :class="[
            'inline-flex items-center rounded-radius-full border px-[9px] py-1 text-[11px] font-medium tracking-[0.08em]',
            toneClasses[tone],
        ]"
    >
        <slot />
    </span>
</template>
