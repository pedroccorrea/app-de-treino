<script setup>
// Fim de um bloco do treino (exercício sozinho ou bi-set/tri-set): o aviso
// de concluído, o "Próximo Exercício →" (ou "🔥 Finalizar Treino" no último
// bloco) e a opção de fazer mais uma série/rodada. Usado pelo
// `ExerciseActiveCard` e pelo `SupersetRoundCard`.
defineProps({
    title: {
        type: String,
        default: 'Exercício concluído!',
    },
    description: {
        type: String,
        default: null,
    },
    isLast: {
        type: Boolean,
        default: false,
    },
    extraLabel: {
        type: String,
        default: '+ Adicionar série extra',
    },
});

defineEmits(['next', 'finish-workout', 'add-extra']);
</script>

<template>
    <div class="mt-4 space-y-3">
        <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-center">
            <p class="text-2xl">🎉</p>
            <p class="mt-1 font-bold text-emerald-400">{{ title }}</p>
            <p v-if="description" class="text-xs text-gray-500 mt-0.5">
                {{ description }}
            </p>
        </div>

        <button
            v-if="!isLast"
            @click="$emit('next')"
            class="flex w-full items-center justify-center gap-2 rounded-2xl bg-gray-800 py-3.5 text-sm font-bold text-white transition hover:bg-gray-700"
        >
            Próximo Exercício →
        </button>
        <button
            v-else
            @click="$emit('finish-workout')"
            class="flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-orange-500 to-red-500 py-3.5 text-sm font-bold text-white shadow-lg shadow-orange-500/25 transition hover:from-orange-600 hover:to-red-600"
        >
            🔥 Finalizar Treino
        </button>

        <button
            @click="$emit('add-extra')"
            class="flex w-full items-center justify-center gap-2 rounded-2xl border border-dashed border-gray-700 py-2.5 text-sm text-gray-400 transition hover:border-violet-500/50 hover:text-violet-400"
        >
            {{ extraLabel }}
        </button>
    </div>
</template>
