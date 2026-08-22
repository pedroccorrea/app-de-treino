<script setup>
defineProps({
    status: {
        type: String,
        required: true, // idle | loading | success | error
    },
    recommendations: {
        type: Array,
        default: () => [],
    },
    errorMessage: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['generate']);
</script>

<template>
    <div
        class="mb-4 overflow-hidden rounded-2xl border border-violet-200 bg-violet-50/50 dark:border-violet-500/20 dark:bg-violet-500/5"
    >
        <div class="flex items-center justify-between gap-3 p-4">
            <div class="flex items-center gap-2">
                <span class="text-lg">🧠</span>
                <div>
                    <p
                        class="text-sm font-semibold text-gray-900 dark:text-gray-100"
                    >
                        Personal Trainer Digital
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Receba sugestões de progressão de carga baseadas no
                        seu histórico
                    </p>
                </div>
            </div>

            <button
                v-if="status !== 'success'"
                type="button"
                :disabled="status === 'loading'"
                @click="emit('generate')"
                class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-xl border border-violet-600 px-3 py-2 text-xs font-semibold text-violet-600 transition hover:bg-violet-100 disabled:cursor-not-allowed disabled:opacity-60 dark:text-violet-400 dark:hover:bg-violet-500/10"
            >
                {{
                    status === 'loading'
                        ? 'Analisando...'
                        : 'Gerar Sugestões'
                }}
            </button>
            <button
                v-else
                type="button"
                @click="emit('generate')"
                class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-xl px-3 py-2 text-xs font-semibold text-violet-600 transition hover:bg-violet-100 dark:text-violet-400 dark:hover:bg-violet-500/10"
                title="Gerar novas sugestões"
            >
                🔄 Atualizar
            </button>
        </div>

        <p
            v-if="status === 'error'"
            class="border-t border-violet-200 px-4 py-3 text-sm text-red-600 dark:border-violet-500/20 dark:text-red-400"
        >
            {{ errorMessage }}
        </p>

        <ul
            v-else-if="status === 'success' && recommendations.length"
            class="space-y-2 border-t border-violet-200 p-4 dark:border-violet-500/20"
        >
            <li
                v-for="(recommendation, index) in recommendations"
                :key="index"
                class="rounded-xl bg-white p-3 shadow-sm dark:bg-gray-800"
            >
                <p
                    class="text-sm font-semibold text-gray-900 dark:text-gray-100"
                >
                    {{ recommendation.exercise_name }}
                </p>
                <p
                    class="mt-1 text-sm font-medium text-violet-600 dark:text-violet-400"
                >
                    {{ recommendation.current_load }} →
                    {{ recommendation.suggested_load }} ·
                    {{ recommendation.suggested_reps }}
                </p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ recommendation.rationale }}
                </p>
            </li>
        </ul>

        <p
            v-else-if="status === 'success'"
            class="border-t border-violet-200 px-4 py-3 text-sm text-gray-500 dark:border-violet-500/20 dark:text-gray-400"
        >
            Ainda não há histórico suficiente para gerar sugestões.
        </p>
    </div>
</template>
