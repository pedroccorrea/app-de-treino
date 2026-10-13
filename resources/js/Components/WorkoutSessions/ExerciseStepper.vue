<script setup>
import { computed } from 'vue';
import { roundTitle } from '@/Utils/supersetRounds';

// Stepper do treino em andamento: uma posição por BLOCO (Phase 54). Exercício
// sozinho é a bolinha de sempre; um bi-set/tri-set ocupa uma posição só, uma
// cápsula com um traço por rodada (os concluídos em verde). Navegar emite o
// índice do primeiro exercício do bloco — o mesmo `currentIndex` da página,
// que abre a rodada atual do grupo.
const props = defineProps({
    steps: {
        // Cada item: { key, index, name, label, done, rounds }
        // `label`/`rounds` ({ current, total, done }) só nos grupos; null no sozinho.
        type: Array,
        required: true,
    },
    currentStep: {
        type: Number,
        required: true,
    },
    // Ficha com algum grupo: o contador passa a contar etapas (blocos), não
    // exercícios. Sem grupo, o texto é o mesmo de sempre.
    hasGroups: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['navigate']);

const current = computed(() => props.steps[props.currentStep] ?? null);

const goTo = (stepIndex) => {
    const step = props.steps[stepIndex];
    if (step) emit('navigate', step.index);
};

const groupStatus = computed(() => {
    const step = current.value;
    if (!step?.label) return null;

    return step.rounds?.current
        ? roundTitle(step.label, step.rounds.current, step.rounds.total)
        : `${step.label} concluído`;
});
</script>

<template>
    <div class="flex-shrink-0 border-b border-gray-800 bg-gray-900/60 px-4 py-3">
        <div class="flex items-center justify-between gap-2">
            <button
                @click="goTo(currentStep - 1)"
                :disabled="currentStep === 0"
                aria-label="Etapa anterior"
                class="flex h-9 w-9 items-center justify-center rounded-xl bg-gray-800 text-gray-400 transition hover:bg-gray-700 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
            </button>

            <div class="flex flex-1 items-center justify-center gap-1.5 overflow-x-auto px-1">
                <template v-for="(step, idx) in steps" :key="step.key">
                    <!-- Bi-set/tri-set: cápsula com o progresso pelas rodadas. -->
                    <button
                        v-if="step.label"
                        @click="goTo(idx)"
                        :class="[
                            'flex h-3.5 flex-shrink-0 items-center gap-0.5 rounded-full border px-1 transition-all duration-300',
                            idx === currentStep
                                ? 'border-violet-500'
                                : step.done
                                  ? 'border-emerald-400'
                                  : 'border-gray-600 hover:border-gray-500',
                        ]"
                        :title="`${step.label}: ${step.name}`"
                        :aria-label="`${step.label}: ${step.name}`"
                    >
                        <span
                            v-for="round in step.rounds?.total ?? 0"
                            :key="round"
                            :class="[
                                'h-1.5 rounded-full transition-all duration-300',
                                idx === currentStep ? 'w-2.5' : 'w-1.5',
                                round <= (step.rounds?.done ?? 0)
                                    ? 'bg-emerald-400'
                                    : idx === currentStep && round === step.rounds?.current
                                      ? 'bg-violet-500'
                                      : 'bg-gray-600',
                            ]"
                        />
                    </button>
                    <button
                        v-else
                        @click="goTo(idx)"
                        :class="[
                            'h-2.5 rounded-full transition-all duration-300 flex-shrink-0',
                            idx === currentStep
                                ? 'w-6 bg-violet-500'
                                : step.done
                                  ? 'w-2.5 bg-emerald-400'
                                  : 'w-2.5 bg-gray-600 hover:bg-gray-500',
                        ]"
                        :title="step.name"
                    />
                </template>
            </div>

            <button
                @click="goTo(currentStep + 1)"
                :disabled="currentStep === steps.length - 1"
                aria-label="Próxima etapa"
                class="flex h-9 w-9 items-center justify-center rounded-xl bg-gray-800 text-gray-400 transition hover:bg-gray-700 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>

        <p class="mt-2 text-center text-sm font-semibold text-white truncate px-10">
            {{ current?.name }}
        </p>
        <p v-if="hasGroups" class="mt-0.5 truncate text-center text-xs text-gray-500 [font-variant-numeric:tabular-nums]">
            Etapa {{ currentStep + 1 }} de {{ steps.length }}<template v-if="groupStatus"> · {{ groupStatus }}</template>
        </p>
        <p v-else class="mt-0.5 text-center text-xs text-gray-500">
            Exercício {{ currentStep + 1 }} de {{ steps.length }}
        </p>
    </div>
</template>
