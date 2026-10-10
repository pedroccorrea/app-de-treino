<script setup>
import BaseCard from '@/Components/UI/BaseCard.vue';
import { formatSet, formatVolume } from '@/Utils/historyFormat';
import { Link } from '@inertiajs/vue3';

defineProps({
    // { exercise_id, name, volume, sets: [{ id, set_number, weight, reps, is_warmup }] }
    exercise: {
        type: Object,
        required: true,
    },
    // Link para o histórico por exercício que já existe (`exercises.history`).
    historyHref: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <BaseCard>
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h3 class="text-base font-semibold text-text-primary">{{ exercise.name }}</h3>
                <p v-if="exercise.volume > 0" class="mt-0.5 text-xs text-text-tertiary [font-variant-numeric:tabular-nums]">
                    Volume {{ formatVolume(exercise.volume) }}
                </p>
            </div>

            <Link
                :href="historyHref"
                class="inline-flex min-h-[48px] shrink-0 items-center gap-1 rounded-radius-md px-2 text-xs font-medium text-text-secondary transition hover:text-accent-text-soft"
            >
                Evolução
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </Link>
        </div>

        <ol class="mt-3 space-y-1.5">
            <li
                v-for="set in exercise.sets"
                :key="set.id"
                class="flex items-center justify-between rounded-radius-sm border border-border-subtle bg-surface-overlay/40 px-3 py-2"
            >
                <span class="text-xs text-text-secondary">
                    Série {{ set.set_number }}
                    <span v-if="set.is_warmup" class="text-text-tertiary">· aquecimento</span>
                </span>
                <span class="text-sm font-medium text-text-primary [font-variant-numeric:tabular-nums]">
                    {{ formatSet(set) }}
                </span>
            </li>
        </ol>
    </BaseCard>
</template>
