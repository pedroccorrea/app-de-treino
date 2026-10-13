<script setup>
// Um exercício na lista do formulário da ficha (criar/editar). As metas
// (séries/repetições) são editadas direto no objeto do `useForm` da página,
// como já era antes da extração; ordem e grupo são decididos pelo pai.
//
// Cores: o formulário da ficha ainda segue o tema claro/escuro do sistema
// (classes `dark:`), e os tokens do design system são só escuros. Por isso
// cada cor de acento tem o par claro (paleta violeta do Tailwind, tingida —
// nunca preenchimento sólido) e o par escuro (tokens). Contraste medido em
// `.ai/rules/design-system.md` → "Auditoria de contraste (Phase 52)".
defineProps({
    exercise: {
        type: Object,
        required: true,
    },
    // Posição exibida (1, 2, 3...).
    number: {
        type: Number,
        required: true,
    },
    canMoveUp: {
        type: Boolean,
        default: false,
    },
    canMoveDown: {
        type: Boolean,
        default: false,
    },
    // Existe um exercício/bloco acima com o qual juntar.
    canJoin: {
        type: Boolean,
        default: false,
    },
    // Está dentro de um bi-set/tri-set.
    grouped: {
        type: Boolean,
        default: false,
    },
    dragging: {
        type: Boolean,
        default: false,
    },
    dragOver: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['move-up', 'move-down', 'remove', 'join', 'separate']);
</script>

<template>
    <li
        :class="[
            'group rounded-2xl border p-4 transition-all duration-200',
            dragOver
                ? 'border-violet-500 bg-violet-50 dark:border-accent dark:bg-accent-muted cursor-copy'
                : 'border-gray-200 bg-gray-50 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-900/50 cursor-grab active:cursor-grabbing',
            dragging ? 'opacity-40 scale-[0.98]' : 'opacity-100',
        ]"
    >
        <div class="flex items-start justify-between gap-3 mb-3">
            <div class="flex min-w-0 items-center gap-3 select-none">
                <span
                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-violet-100 text-xs font-bold text-violet-700 dark:bg-accent-muted dark:text-accent-label"
                >
                    {{ number }}
                </span>
                <div class="min-w-0">
                    <p
                        class="text-sm font-bold text-gray-900 dark:text-gray-100"
                    >
                        {{ exercise.name }}
                    </p>
                    <span
                        class="inline-block mt-0.5 rounded-md bg-gray-200/80 px-2 py-0.5 text-[11px] font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                    >
                        {{ exercise.primary_muscle }}
                    </span>
                </div>
            </div>

            <!-- Setas + remover. Em tela de toque (sem hover) as setas ficam
                 sempre visíveis — são o jeito de mudar a ordem dentro de um
                 bi-set no celular. Só com mouse elas aparecem ao passar o
                 cursor, como antes. -->
            <div class="flex shrink-0 items-center gap-0.5">
                <div
                    class="flex items-center gap-0.5 transition-opacity [@media(hover:hover)]:opacity-0 [@media(hover:hover)]:group-hover:opacity-100 [@media(hover:hover)]:focus-within:opacity-100"
                >
                    <button
                        type="button"
                        @click.stop="emit('move-up')"
                        :disabled="!canMoveUp"
                        class="flex h-12 w-12 items-center justify-center rounded-lg text-gray-500 transition hover:bg-violet-500/10 hover:text-violet-700 disabled:opacity-20 disabled:cursor-not-allowed dark:text-gray-400 dark:hover:bg-accent-muted dark:hover:text-accent-text-soft"
                        title="Mover para cima"
                        aria-label="Mover para cima"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        @click.stop="emit('move-down')"
                        :disabled="!canMoveDown"
                        class="flex h-12 w-12 items-center justify-center rounded-lg text-gray-500 transition hover:bg-violet-500/10 hover:text-violet-700 disabled:opacity-20 disabled:cursor-not-allowed dark:text-gray-400 dark:hover:bg-accent-muted dark:hover:text-accent-text-soft"
                        title="Mover para baixo"
                        aria-label="Mover para baixo"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                </div>

                <!-- Trash icon (remove exercise) -->
                <button
                    type="button"
                    @click.stop="emit('remove')"
                    class="flex h-12 w-12 items-center justify-center rounded-lg text-gray-500 transition hover:bg-red-500/10 hover:text-red-600 focus:outline-none dark:text-gray-400 dark:hover:text-danger"
                    title="Remover exercício"
                    aria-label="Remover exercício"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 pt-2 border-t border-gray-200/60 dark:border-gray-800">
            <div>
                <label
                    class="block text-xs font-semibold text-gray-600 dark:text-gray-400"
                >
                    Séries
                </label>
                <input
                    v-model.number="exercise.target_sets"
                    type="number"
                    min="1"
                    max="20"
                    placeholder="3"
                    class="mt-1 block w-full rounded-xl border-gray-300 py-1.5 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:focus:border-accent dark:focus:ring-accent"
                />
            </div>

            <div>
                <label
                    class="block text-xs font-semibold text-gray-600 dark:text-gray-400"
                >
                    Repetições
                </label>
                <input
                    v-model="exercise.target_reps"
                    type="text"
                    placeholder="10 ou 8-12"
                    class="mt-1 block w-full rounded-xl border-gray-300 py-1.5 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:focus:border-accent dark:focus:ring-accent"
                />
            </div>
        </div>

        <!-- Exercícios combinados (bi-set/tri-set) -->
        <div v-if="canJoin || grouped" class="mt-3 flex flex-wrap justify-end gap-2">
            <button
                v-if="canJoin"
                type="button"
                class="inline-flex h-12 items-center gap-1.5 rounded-radius-full border border-violet-300 bg-violet-50 px-4 text-xs font-semibold text-violet-700 transition hover:border-violet-500 dark:border-border-accent dark:bg-accent-muted dark:text-accent-text-strong dark:hover:brightness-110"
                @click.stop="emit('join')"
            >
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7" />
                </svg>
                Juntar com o de cima
            </button>
            <button
                v-if="grouped"
                type="button"
                class="inline-flex h-12 items-center gap-1.5 rounded-radius-full border border-gray-300 px-4 text-xs font-semibold text-gray-600 transition hover:border-violet-500 hover:text-gray-900 dark:border-border-subtle dark:text-gray-300 dark:hover:border-border-accent dark:hover:text-text-primary"
                @click.stop="emit('separate')"
            >
                Separar
            </button>
        </div>
    </li>
</template>
