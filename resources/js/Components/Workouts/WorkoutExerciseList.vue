<script setup>
import SupersetBlock from '@/Components/Workouts/SupersetBlock.vue';
import { useExerciseReorder } from '@/Composables/useExerciseReorder';
import { formatRepTarget } from '@/Utils/repTarget';
import { buildExerciseBlocks } from '@/Utils/supersetGroups';
import { computed } from 'vue';

// Lista arrastável dos exercícios na página da ficha (`Workouts/Show`).
// Bi-sets/tri-sets aparecem como um bloco com rótulo; exercícios sozinhos
// ficam exatamente como antes. Só calcula a nova ordem e a emite em
// `reorder` — quem grava (rota `workouts.reorder`) é a página.
const props = defineProps({
    exercises: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['reorder']);

const blocks = computed(() => buildExerciseBlocks(props.exercises));

// Mesmo comportamento do formulário da ficha: sozinho pula o bloco vizinho
// inteiro; dentro do grupo só troca a ordem interna; arrastar um exercício
// do grupo para fora leva o bloco inteiro.
const {
    draggingIndex,
    dragOverIndex,
    onDragStart,
    onDragOver,
    onDragEnd,
    onDrop,
    canMove,
    move,
    canMoveBlock,
    moveBlock,
} = useExerciseReorder(() => props.exercises, (list) => emit('reorder', list));

const blockContains = (block, index) => index !== null && block.items.some((item) => item.index === index);

const isBlockHighlighted = (block) =>
    blockContains(block, dragOverIndex.value) && !blockContains(block, draggingIndex.value);

const formatTarget = (exercise) => {
    const parts = [];
    if (exercise.target_sets) {
        parts.push(`${exercise.target_sets} ${exercise.target_sets === 1 ? 'série' : 'séries'}`);
    }
    if (exercise.target_reps) {
        parts.push(formatRepTarget(exercise.target_reps) + ' reps');
    }
    return parts.length ? parts.join(' × ') : 'Metas não definidas';
};
</script>

<template>
    <div class="space-y-2">
        <SupersetBlock
            v-for="(block, blockIndex) in blocks"
            :key="block.key"
            :label="block.label"
            :can-move-up="canMoveBlock(blockIndex, -1)"
            :can-move-down="canMoveBlock(blockIndex, 1)"
            :highlighted="isBlockHighlighted(block)"
            @move-up="moveBlock(blockIndex, -1)"
            @move-down="moveBlock(blockIndex, 1)"
        >
            <!-- the entire card is the drag target -->
            <ol class="space-y-2">
                <li
                    v-for="{ exercise, index } in block.items"
                    :key="exercise.id"
                    draggable="true"
                    @dragstart="(e) => onDragStart(e, index)"
                    @dragover="(e) => onDragOver(e, index)"
                    @drop="onDrop(index)"
                    @dragend="onDragEnd"
                    :class="[
                        'group flex items-center gap-3 rounded-radius-md border p-4 transition-all duration-200',
                        dragOverIndex === index && draggingIndex !== index && !isBlockHighlighted(block)
                            ? 'border-accent bg-accent-muted cursor-copy'
                            : 'border-border-subtle bg-surface-overlay/40 hover:border-border-accent cursor-grab active:cursor-grabbing',
                        draggingIndex === index ? 'opacity-40 scale-[0.98]' : 'opacity-100',
                    ]"
                >
                    <!-- Order number -->
                    <span
                        class="flex h-7 w-7 shrink-0 select-none items-center justify-center rounded-radius-full bg-accent-muted text-xs font-bold text-accent-label"
                    >
                        {{ index + 1 }}
                    </span>

                    <!-- Exercise info -->
                    <div class="min-w-0 flex-1 select-none">
                        <p class="font-medium text-text-primary">
                            {{ exercise.name }}
                        </p>
                        <p class="mt-0.5 text-xs text-text-secondary">
                            {{ exercise.primary_muscle }}
                        </p>
                        <p class="mt-1.5 text-sm font-semibold text-accent-text-soft">
                            {{ formatTarget(exercise) }}
                        </p>
                    </div>

                    <!-- Setas: sempre visíveis em tela de toque (sem hover) — são o
                         jeito de mudar a ordem dentro de um bi-set no celular. Com
                         mouse aparecem ao passar o cursor, como antes. -->
                    <div class="flex shrink-0 flex-col gap-0.5 transition-opacity [@media(hover:hover)]:opacity-0 [@media(hover:hover)]:group-hover:opacity-100 [@media(hover:hover)]:focus-within:opacity-100">
                        <button
                            type="button"
                            @click.stop="move(index, -1)"
                            :disabled="!canMove(index, -1)"
                            class="flex h-12 w-12 items-center justify-center rounded-radius-sm text-text-body transition hover:bg-accent-muted hover:text-accent-text-soft disabled:cursor-not-allowed disabled:opacity-20"
                            title="Mover para cima"
                            aria-label="Mover para cima"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            @click.stop="move(index, 1)"
                            :disabled="!canMove(index, 1)"
                            class="flex h-12 w-12 items-center justify-center rounded-radius-sm text-text-body transition hover:bg-accent-muted hover:text-accent-text-soft disabled:cursor-not-allowed disabled:opacity-20"
                            title="Mover para baixo"
                            aria-label="Mover para baixo"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>
                </li>
            </ol>
        </SupersetBlock>
    </div>
</template>
