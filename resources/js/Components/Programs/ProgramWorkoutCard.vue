<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    workout: {
        type: Object,
        required: true,
    },
    returnTo: {
        type: String,
        default: '',
    },
});

defineEmits(['open', 'delete']);
</script>

<template>
    <div
        @click="$emit('open', workout)"
        class="group flex cursor-pointer items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition duration-150 hover:-translate-y-0.5 hover:border-violet-500/40 hover:shadow-md dark:border-gray-700 dark:bg-gray-800"
    >
        <div class="min-w-0">
            <div class="flex items-center gap-1.5">
                <h3 class="truncate text-base font-bold text-gray-900 transition group-hover:text-violet-600 dark:text-gray-100 dark:group-hover:text-violet-400">
                    {{ workout.name }}
                </h3>
                <Link
                    :href="route('workouts.edit', { workout: workout.id, return_to: returnTo })"
                    @click.stop
                    class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-lg text-gray-400 transition hover:bg-violet-500/10 hover:text-violet-600 dark:hover:text-violet-400"
                    title="Editar treino"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                </Link>
            </div>
            <p v-if="workout.description" class="mt-0.5 truncate text-sm text-gray-500 dark:text-gray-400">
                {{ workout.description }}
            </p>
            <p class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">
                {{ workout.exercises_count }}
                {{ workout.exercises_count === 1 ? 'exercício' : 'exercícios' }}
            </p>
        </div>

        <button
            type="button"
            @click.stop="$emit('delete', workout)"
            class="inline-flex shrink-0 items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-red-500 transition hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/10"
            title="Excluir ficha"
        >
            🗑️ Excluir
        </button>
    </div>
</template>
