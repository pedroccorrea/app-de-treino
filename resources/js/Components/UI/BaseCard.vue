<script setup>
defineProps({
    padded: {
        type: Boolean,
        default: true,
    },
});
</script>

<template>
    <div
        :class="[
            'rounded-radius-lg border border-border-subtle bg-surface-raised',
            padded ? 'p-4' : '',
        ]"
    >
        <slot />
    </div>
</template>
