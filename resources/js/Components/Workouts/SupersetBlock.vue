<script setup>
import BaseBadge from '@/Components/UI/BaseBadge.vue';

// Moldura de um grupo combinado (bi-set/tri-set) na lista de exercícios da
// ficha. Sem `label` (exercício sozinho) só repassa o conteúdo, sem moldura:
// fichas sem grupo ficam visualmente iguais a antes.
//
// O fundo da moldura é opaco (tinta de acento sobre `--surface-raised`), não
// só `bg-accent-muted` translúcido: o formulário da ficha ainda tem tema claro
// (cartão branco) e a tinta sobre branco deixaria o rótulo ilegível. Assim o
// fundo efetivo é o mesmo no formulário e na página da ficha — contraste
// medido em .ai/rules/design-system.md (Phase 52).
const frameBackground = 'linear-gradient(var(--accent-muted), var(--accent-muted)), var(--surface-raised)';

defineProps({
    // "Bi-set", "Tri-set", "Combinado" — ou null para exercício sozinho.
    label: {
        type: String,
        default: null,
    },
    canMoveUp: {
        type: Boolean,
        default: false,
    },
    canMoveDown: {
        type: Boolean,
        default: false,
    },
    // Algo de fora do bloco está sendo arrastado por cima dele: o bloco
    // inteiro acende, porque é o bloco inteiro que vai mudar de lugar.
    highlighted: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['move-up', 'move-down']);
</script>

<template>
    <div v-if="!label">
        <slot />
    </div>

    <section
        v-else
        :aria-label="label"
        :class="[
            'rounded-radius-lg border p-2 transition-colors duration-200',
            highlighted ? 'border-accent shadow-md shadow-accent-glow-soft' : 'border-border-accent',
        ]"
        :style="{ background: frameBackground }"
    >
        <header class="flex items-center justify-between gap-2 px-2 pb-2 pt-1">
            <div class="flex min-w-0 items-center gap-2">
                <BaseBadge tone="accent">{{ label }}</BaseBadge>
                <span class="truncate text-xs text-text-body">Exercícios combinados</span>
            </div>

            <div class="flex shrink-0 items-center gap-1">
                <button
                    type="button"
                    :disabled="!canMoveUp"
                    class="flex h-12 w-12 items-center justify-center rounded-radius-sm text-text-body transition hover:bg-accent-muted hover:text-accent-text-soft disabled:cursor-not-allowed disabled:opacity-20"
                    :title="`Mover ${label} para cima`"
                    :aria-label="`Mover ${label} para cima`"
                    @click.stop="emit('move-up')"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7" />
                    </svg>
                </button>
                <button
                    type="button"
                    :disabled="!canMoveDown"
                    class="flex h-12 w-12 items-center justify-center rounded-radius-sm text-text-body transition hover:bg-accent-muted hover:text-accent-text-soft disabled:cursor-not-allowed disabled:opacity-20"
                    :title="`Mover ${label} para baixo`"
                    :aria-label="`Mover ${label} para baixo`"
                    @click.stop="emit('move-down')"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            </div>
        </header>

        <div class="space-y-2">
            <slot />
        </div>
    </section>
</template>
