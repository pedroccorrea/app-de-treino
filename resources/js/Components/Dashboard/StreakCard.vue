<script setup>
import SetwaveLogo from '@/Components/Brand/SetwaveLogo.vue';

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
        <div v-if="streak > 0">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                ⚡ Sequência de Treinos
            </p>
            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">
                {{ streak }} {{ streak === 1 ? 'dia seguido' : 'dias seguidos' }}
            </p>
        </div>
        <div v-else>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Sequência de Treinos
            </p>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Você ainda não tem histórico de treinos. Complete um treino para começar sua sequência.
            </p>
        </div>
        <span v-if="streak > 0" class="text-4xl">🔥</span>
        <SetwaveLogo v-else :size="32" variant="mark" />
    </div>
</template>
