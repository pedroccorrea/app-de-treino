<script setup>
defineProps({
    workout: {
        type: Object,
        required: true,
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
            <h3 class="truncate text-base font-bold text-gray-900 transition group-hover:text-violet-600 dark:text-gray-100 dark:group-hover:text-violet-400">
                {{ workout.name }}
            </h3>
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
