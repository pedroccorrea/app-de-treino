<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    workouts: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <div
        v-if="workouts.length"
        class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800"
    >
        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            Treinos Ativos
        </h3>
        <ul class="mt-3 space-y-2">
            <li v-for="workout in workouts" :key="workout.id">
                <Link
                    :href="route('workouts.show', workout.id)"
                    class="flex items-center justify-between rounded-xl px-3 py-2 transition hover:bg-gray-50 dark:hover:bg-gray-700/50"
                >
                    <div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ workout.name }}
                        </p>
                        <p
                            v-if="workout.days_of_week_labels.length"
                            class="text-xs text-gray-500 dark:text-gray-400"
                        >
                            📅 {{ workout.days_of_week_labels.join(', ') }}
                        </p>
                    </div>
                    <span class="text-xs font-bold text-violet-600 dark:text-violet-400">
                        {{ workout.exercises_count }}
                        {{ workout.exercises_count === 1 ? 'exercício' : 'exercícios' }}
                    </span>
                </Link>
            </li>
        </ul>
    </div>
</template>
