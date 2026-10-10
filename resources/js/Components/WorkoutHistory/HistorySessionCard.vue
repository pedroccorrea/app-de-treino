<script setup>
import BaseCard from '@/Components/UI/BaseCard.vue';
import {
    DELETED_WORKOUT_LABEL,
    formatDuration,
    formatSessionDate,
    formatVolume,
} from '@/Utils/historyFormat';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    // { id, workout_name, completed_at, duration_minutes, exercise_names, exercise_count, set_count, volume }
    session: {
        type: Object,
        required: true,
    },
    href: {
        type: String,
        required: true,
    },
});

const MAX_VISIBLE_NAMES = 3;

const visibleNames = computed(() => props.session.exercise_names.slice(0, MAX_VISIBLE_NAMES).join(' · '));
const hiddenCount = computed(() => Math.max(0, props.session.exercise_names.length - MAX_VISIBLE_NAMES));

const stats = computed(() =>
    [
        `${props.session.exercise_count} ${props.session.exercise_count === 1 ? 'exercício' : 'exercícios'}`,
        `${props.session.set_count} ${props.session.set_count === 1 ? 'série' : 'séries'}`,
        props.session.volume > 0 ? formatVolume(props.session.volume) : null,
        formatDuration(props.session.duration_minutes),
    ].filter(Boolean),
);
</script>

<template>
    <Link :href="href" class="block rounded-radius-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-accent">
        <BaseCard class="transition hover:border-border-accent active:bg-surface-overlay">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-xs uppercase tracking-[0.12em] text-text-secondary">
                        {{ formatSessionDate(session.completed_at) }}
                    </p>
                    <h3
                        class="mt-1 truncate text-base font-semibold"
                        :class="session.workout_name ? 'text-text-primary' : 'italic text-text-secondary'"
                    >
                        {{ session.workout_name ?? DELETED_WORKOUT_LABEL }}
                    </h3>
                </div>

                <svg class="mt-1 h-5 w-5 shrink-0 text-text-tertiary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </div>

            <p v-if="visibleNames" class="mt-2 line-clamp-2 text-sm text-text-body">
                {{ visibleNames }}<span v-if="hiddenCount" class="text-text-tertiary"> +{{ hiddenCount }}</span>
            </p>

            <ul class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-xs text-text-tertiary [font-variant-numeric:tabular-nums]">
                <li v-for="stat in stats" :key="stat">{{ stat }}</li>
            </ul>
        </BaseCard>
    </Link>
</template>
