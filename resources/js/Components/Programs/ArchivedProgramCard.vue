<script setup>
defineProps({
    program: {
        type: Object,
        required: true,
    },
});

defineEmits(['reactivate', 'delete']);
</script>

<template>
    <div
        class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
    >
        <div class="flex items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                    {{ program.name }}
                </h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Arquivado em {{ program.archived_at }}
                </p>
            </div>
        </div>

        <ul
            v-if="program.workouts.length"
            class="mt-4 space-y-1 border-t border-gray-100 pt-3 dark:border-gray-700"
        >
            <li
                v-for="workout in program.workouts"
                :key="workout.id"
                class="text-sm text-gray-600 dark:text-gray-400"
            >
                • {{ workout.name }}
            </li>
        </ul>
        <p v-else class="mt-4 text-sm text-gray-500 dark:text-gray-400">
            Nenhuma ficha neste programa.
        </p>

        <div class="mt-4 flex items-center gap-2 border-t border-gray-100 pt-3 dark:border-gray-700">
            <button
                type="button"
                @click="$emit('reactivate', program)"
                class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-violet-600 transition hover:bg-violet-50 dark:text-violet-400 dark:hover:bg-violet-500/10"
            >
                ⚡ Reativar Programa
            </button>
            <button
                type="button"
                @click="$emit('delete', program)"
                class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-red-500 transition hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/10"
            >
                🗑️ Excluir
            </button>
        </div>
    </div>
</template>
