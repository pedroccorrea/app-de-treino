<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    activeWorkouts: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <div
        class="rounded-2xl border border-dashed border-gray-300 bg-white p-6 text-center dark:border-gray-700 dark:bg-gray-800"
    >
        <p class="text-3xl">😌</p>
        <h3 class="mt-2 text-lg font-bold text-gray-900 dark:text-gray-100">
            Hoje é dia de descanso!
        </h3>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Ou escolha um treino abaixo
        </p>

        <div v-if="activeWorkouts.length" class="mt-4 flex flex-wrap justify-center gap-2">
            <Link
                v-for="workout in activeWorkouts"
                :key="workout.id"
                :href="route('workouts.start', workout.id)"
                method="post"
                as="button"
                type="button"
                class="rounded-xl bg-violet-500/10 px-3 py-2 text-sm font-semibold text-violet-600 transition hover:bg-violet-500/20 dark:text-violet-400"
            >
                {{ workout.name }}
            </Link>
        </div>
    </div>
</template>
