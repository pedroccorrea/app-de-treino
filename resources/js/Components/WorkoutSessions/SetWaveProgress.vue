<script setup>
import { computed } from 'vue';

const props = defineProps({
    setNumber: {
        type: Number,
        required: true,
    },
    totalSets: {
        type: Number,
        required: true,
    },
});

const percent = computed(() =>
    props.totalSets > 0 ? Math.min(100, ((props.setNumber - 1) / props.totalSets) * 100) : 0,
);

const dashArray = computed(() => `${percent.value.toFixed(1)} 100`);
const dotX = computed(() => 6 + (326 * percent.value) / 100);

const remaining = computed(() => Math.max(0, props.totalSets - props.setNumber));
const remainingLabel = computed(() =>
    remaining.value === 1 ? '1 restante hoje' : `${remaining.value} restantes hoje`,
);
</script>

<template>
    <div class="flex flex-col gap-2.5">
        <div class="flex items-baseline justify-between">
            <span class="text-xs uppercase tracking-[0.16em] text-[#8A8A99]">
                Série {{ setNumber }} de {{ totalSets }}
            </span>
            <span class="text-xs tracking-[0.04em] text-[#5A5A68]">{{ remainingLabel }}</span>
        </div>
        <svg width="338" height="34" viewBox="0 0 338 34" fill="none" class="block w-full">
            <path
                d="M0 17C14 3 28 3 42 17S70 31 84.5 17s28-14 42 0 28 14 42.5 0 28-14 42 0 28 14 42.5 0 28-14 42 0"
                stroke="rgba(255,255,255,.10)"
                stroke-width="1.4"
                stroke-linecap="round"
            />
            <path
                pathLength="100"
                :stroke-dasharray="dashArray"
                d="M0 17C14 3 28 3 42 17S70 31 84.5 17s28-14 42 0 28 14 42.5 0 28-14 42 0 28 14 42.5 0 28-14 42 0"
                stroke="#8B5CF6"
                stroke-width="1.8"
                stroke-linecap="round"
                style="transition: stroke-dasharray 0.6s cubic-bezier(0.22, 1, 0.36, 1); filter: drop-shadow(0 0 8px rgba(139, 92, 246, 0.45))"
            />
            <circle
                :cx="dotX"
                cy="17"
                r="3.5"
                fill="#8B5CF6"
                style="transition: cx 0.6s cubic-bezier(0.22, 1, 0.36, 1)"
            />
        </svg>
    </div>
</template>
