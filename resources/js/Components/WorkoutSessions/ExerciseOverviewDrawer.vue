<script setup>
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import ExerciseOverviewItem from '@/Components/WorkoutSessions/ExerciseOverviewItem.vue';

// Visão geral do treino, um item por BLOCO (Phase 54): exercício sozinho
// igual a sempre; os exercícios de um bi-set/tri-set aparecem juntos numa
// moldura com o rótulo e a rodada. Tocar no bloco emite o índice do primeiro
// exercício dele — a página abre a rodada atual do grupo.
defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    blocks: {
        // Cada item: { key, index, label, status, done, exercises }
        // `exercises`: [{ id, number, name, primary_muscle, done, doneCount, totalSets }]
        // `label`/`status` ("Rodada 2 de 3", "Concluído") só nos grupos.
        type: Array,
        required: true,
    },
    currentBlock: {
        type: Number,
        required: true,
    },
});

defineEmits(['close', 'select']);
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="show"
                class="fixed inset-0 z-[60] flex items-end justify-center bg-black/70 backdrop-blur-sm"
                @click.self="$emit('close')"
            >
                <Transition name="slide-up">
                    <div
                        v-if="show"
                        class="w-full max-w-lg rounded-t-3xl bg-gray-900 border-t border-gray-700 p-5 max-h-[70vh] overflow-y-auto"
                    >
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-bold text-white">Visão Geral do Treino</h3>
                            <button @click="$emit('close')" class="text-gray-400 hover:text-white transition">✕</button>
                        </div>
                        <ul class="space-y-2">
                            <li
                                v-for="(block, idx) in blocks"
                                :key="block.key"
                                @click="$emit('select', block.index)"
                                :class="[
                                    'rounded-2xl border p-3 cursor-pointer transition',
                                    idx === currentBlock
                                        ? 'border-violet-500 bg-violet-500/10'
                                        : block.done
                                          ? 'border-emerald-500/30 bg-emerald-500/10'
                                          : block.label
                                            ? 'border-border-accent hover:border-accent'
                                            : 'border-gray-800 hover:border-gray-700',
                                ]"
                            >
                                <!-- Bi-set/tri-set: o grupo inteiro como um bloco só. -->
                                <template v-if="block.label">
                                    <div class="mb-3 flex items-center justify-between gap-2">
                                        <BaseBadge tone="accent">{{ block.label }}</BaseBadge>
                                        <span class="text-xs text-text-secondary [font-variant-numeric:tabular-nums]">
                                            {{ block.status }}
                                        </span>
                                    </div>
                                    <div class="space-y-3">
                                        <ExerciseOverviewItem
                                            v-for="ex in block.exercises"
                                            :key="ex.id"
                                            :number="ex.number"
                                            :name="ex.name"
                                            :primary-muscle="ex.primary_muscle"
                                            :done="ex.done"
                                            :current="idx === currentBlock"
                                            :done-count="ex.doneCount"
                                            :total-sets="ex.totalSets"
                                        />
                                    </div>
                                </template>
                                <ExerciseOverviewItem
                                    v-else
                                    :number="block.exercises[0].number"
                                    :name="block.exercises[0].name"
                                    :primary-muscle="block.exercises[0].primary_muscle"
                                    :done="block.exercises[0].done"
                                    :current="idx === currentBlock"
                                    :done-count="block.exercises[0].doneCount"
                                    :total-sets="block.exercises[0].totalSets"
                                />
                            </li>
                        </ul>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.slide-up-enter-active,
.slide-up-leave-active {
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.slide-up-enter-from,
.slide-up-leave-to {
    opacity: 0;
    transform: translateY(20px);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
