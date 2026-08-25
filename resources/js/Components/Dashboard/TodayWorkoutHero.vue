<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    workout: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div
        class="overflow-hidden rounded-2xl border border-orange-400/40 bg-gradient-to-br from-orange-500/10 via-red-500/5 to-transparent p-6 shadow-sm dark:border-orange-500/30"
    >
        <p class="text-xs font-bold uppercase tracking-wide text-orange-500 dark:text-orange-400">
            🔥 Treino de Hoje
        </p>
        <h3 class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">
            {{ workout.name }}
        </h3>

        <div v-if="workout.muscle_groups.length" class="mt-3 flex flex-wrap gap-1.5">
            <span
                v-for="muscle in workout.muscle_groups"
                :key="muscle"
                class="rounded-lg bg-white/70 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800/70 dark:text-gray-300"
            >
                {{ muscle }}
            </span>
        </div>

        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            {{ workout.exercises_count }}
            {{ workout.exercises_count === 1 ? 'exercício' : 'exercícios' }}
        </p>

        <Link
            :href="route('workouts.start', workout.id)"
            method="post"
            as="button"
            type="button"
            class="mt-5 flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-orange-500 to-red-500 py-4 text-base font-bold text-white shadow-lg shadow-orange-500/30 transition hover:from-orange-600 hover:to-red-600 active:scale-[0.98]"
        >
            🔥 Iniciar Treino Agora
        </Link>
    </div>
</template>
