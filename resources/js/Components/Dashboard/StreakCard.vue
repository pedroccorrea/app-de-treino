<script setup>
defineProps({
    streak: {
        type: Number,
        default: 0,
    },
});
</script>

<template>
    <div
        class="flex items-center justify-between rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800"
    >
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                ⚡ Sequência de Treinos
            </p>
            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">
                {{ streak }} {{ streak === 1 ? 'dia seguido' : 'dias seguidos' }}
            </p>
        </div>
        <span class="text-4xl">🔥</span>
    </div>
</template>
