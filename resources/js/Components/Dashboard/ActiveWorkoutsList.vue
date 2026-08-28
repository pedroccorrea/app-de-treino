<script setup>
import BaseCard from '@/Components/UI/BaseCard.vue';
import SectionLabel from '@/Components/UI/SectionLabel.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    workouts: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <BaseCard v-if="workouts.length">
        <SectionLabel tone="secondary">Treinos Ativos</SectionLabel>

        <ul class="mt-3 space-y-1">
            <li v-for="workout in workouts" :key="workout.id">
                <Link
                    :href="route('workouts.show', workout.id)"
                    class="flex min-h-[48px] items-center justify-between gap-3 rounded-radius-md px-3 py-2 transition hover:bg-surface-overlay"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-text-primary">
                            {{ workout.name }}
                        </p>
                        <p
                            v-if="workout.days_of_week_labels.length"
                            class="mt-0.5 flex items-center gap-1 text-xs text-text-secondary"
                        >
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                            </svg>
                            {{ workout.days_of_week_labels.join(', ') }}
                        </p>
                    </div>
                    <span class="shrink-0 text-xs font-semibold text-accent-text-soft">
                        {{ workout.exercises_count }}
                        {{ workout.exercises_count === 1 ? 'exercício' : 'exercícios' }}
                    </span>
                </Link>
            </li>
        </ul>
    </BaseCard>
</template>
