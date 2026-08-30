<script setup>
import { router } from '@inertiajs/vue3';

const props = defineProps({
    exerciseId: {
        type: [Number, String],
        required: true,
    },
    ranges: {
        // [{ value: '4w', label: '4S' }, ...]
        type: Array,
        required: true,
    },
    current: {
        type: String,
        required: true,
    },
    returnTo: {
        type: String,
        default: null,
    },
});

const selectRange = (value) => {
    if (value === props.current) return;

    router.get(
        route('exercises.history', props.exerciseId),
        {
            range: value,
            ...(props.returnTo ? { return_to: props.returnTo } : {}),
        },
        { preserveScroll: true, preserveState: true, replace: true },
    );
};
</script>

<template>
    <div class="inline-flex items-center gap-1 rounded-radius-full border border-border-subtle bg-surface-raised p-1">
        <button
            v-for="option in ranges"
            :key="option.value"
            type="button"
            @click="selectRange(option.value)"
            :class="[
                'rounded-radius-full px-4 py-1.5 text-[13px] font-semibold tracking-[0.02em] transition',
                option.value === current
                    ? 'border border-border-accent bg-accent-muted text-accent-text-strong'
                    : 'border border-transparent text-text-secondary hover:text-text-primary',
            ]"
        >
            {{ option.label }}
        </button>
    </div>
</template>
