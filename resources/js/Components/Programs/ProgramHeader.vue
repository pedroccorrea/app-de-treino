<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    activeProgram: {
        type: Object,
        default: null,
    },
});
</script>

<template>
    <div
        class="flex flex-col gap-3 rounded-2xl border border-violet-500/20 bg-violet-500/5 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-violet-500/30 dark:bg-violet-500/10"
    >
        <div>
            <p class="text-xs font-bold uppercase tracking-wide text-violet-600 dark:text-violet-400">
                📋 Programa Ativo
            </p>
            <h3 class="mt-1 text-lg font-bold text-gray-900 dark:text-gray-100">
                {{ activeProgram ? activeProgram.name : 'Nenhum programa ativo' }}
            </h3>
        </div>

        <Link
            :href="route('programs.index')"
            class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-violet-600 px-4 py-2 text-sm font-semibold text-violet-600 transition hover:bg-violet-50 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 dark:text-violet-400 dark:hover:bg-violet-500/10 dark:focus:ring-offset-gray-800"
        >
            🔄 Trocar Programa
        </Link>
    </div>
</template>
