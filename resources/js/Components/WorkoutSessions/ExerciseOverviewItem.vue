<script setup>
// Linha de um exercício na visão geral do treino (`ExerciseOverviewDrawer`):
// número (ou ✓), nome, grupo muscular e séries feitas. A borda/fundo de
// destaque fica com quem envolve a linha — o item do exercício sozinho ou a
// moldura do bi-set/tri-set (Phase 54).
defineProps({
    number: {
        type: Number,
        required: true,
    },
    name: {
        type: String,
        required: true,
    },
    primaryMuscle: {
        type: String,
        default: '',
    },
    done: {
        type: Boolean,
        default: false,
    },
    current: {
        type: Boolean,
        default: false,
    },
    doneCount: {
        type: Number,
        default: 0,
    },
    totalSets: {
        type: Number,
        default: 0,
    },
});
</script>

<template>
    <div class="flex items-center gap-3">
        <span :class="[
            'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
            done
                ? 'bg-emerald-500 text-white'
                : current
                  ? 'bg-violet-600 text-white'
                  : 'bg-gray-800 text-gray-400',
        ]">
            {{ done ? '✓' : number }}
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-white truncate">{{ name }}</p>
            <p class="text-xs text-gray-500">{{ primaryMuscle }}</p>
        </div>
        <p class="text-xs text-gray-500 shrink-0">
            {{ doneCount }}/{{ totalSets }}
        </p>
    </div>
</template>
