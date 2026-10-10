<script setup>
import SetwaveLogo from '@/Components/Brand/SetwaveLogo.vue';
import BaseCard from '@/Components/UI/BaseCard.vue';

defineProps({
    title: {
        type: String,
        required: true,
    },
    message: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <BaseCard class="border-dashed px-6 py-14 text-center">
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-radius-lg bg-accent-muted">
            <SetwaveLogo :size="32" variant="mark" />
        </div>

        <p class="text-base font-semibold text-text-primary">{{ title }}</p>
        <p class="mx-auto mt-2 max-w-sm text-sm text-text-secondary">{{ message }}</p>

        <div v-if="$slots.default" class="mt-6 flex justify-center">
            <slot />
        </div>
    </BaseCard>
</template>
