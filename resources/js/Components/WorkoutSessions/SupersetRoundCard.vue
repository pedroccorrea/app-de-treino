<script setup>
import { computed } from 'vue';
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import BaseCard from '@/Components/UI/BaseCard.vue';
import BlockDoneActions from '@/Components/WorkoutSessions/BlockDoneActions.vue';
import CompleteSetButton from '@/Components/WorkoutSessions/CompleteSetButton.vue';
import SupersetRoundSection from '@/Components/WorkoutSessions/SupersetRoundSection.vue';
import { roundTitle } from '@/Utils/supersetRounds';

// Card da rodada de um bi-set/tri-set no treino em andamento (Phase 53): UM
// card com uma seção por exercício da rodada e um único "Concluir rodada".
// Sem descanso nem troca de tela entre os exercícios do grupo — o descanso só
// começa depois que a página registra a série de todos eles.
//
// Os eventos dos exercícios sobem com o próprio exercício como 1º argumento
// (`adjust-weight(exercise, setNumber, delta)`, etc.), para a página saber de
// quem é cada série.
const props = defineProps({
    // "Bi-set" | "Tri-set" | "Combinado"
    label: {
        type: String,
        required: true,
    },
    // Rodada atual, ou `null` quando o grupo terminou.
    round: {
        type: Number,
        default: null,
    },
    totalRounds: {
        type: Number,
        default: 0,
    },
    // Uma entrada por exercício exibido — props de `SupersetRoundSection`
    // mais o `exercise`: { exercise, setNumber, totalSets, inputs,
    // roundSetDone, doneSets, lastLog, overloadSuggestion }.
    sections: {
        type: Array,
        required: true,
    },
    overloadStatus: {
        type: String,
        default: 'ready',
    },
    saving: {
        type: Boolean,
        default: false,
    },
    // Mensagem quando uma série da rodada não foi salva (online).
    error: {
        type: String,
        default: null,
    },
    exerciseTimerActive: {
        type: Boolean,
        default: false,
    },
    exerciseTimerDisplay: {
        type: String,
        default: '00:00',
    },
    restSeconds: {
        type: Number,
        default: 60,
    },
    isLastBlock: {
        type: Boolean,
        default: false,
    },
});

defineEmits([
    'adjust-weight',
    'adjust-reps',
    'set-weight',
    'set-reps',
    'edit-set',
    'finish-exercise',
    'apply-overload-suggestion',
    'dismiss-overload-suggestion',
    'complete-round',
    'next-exercise',
    'finish-workout',
    'add-extra-round',
]);

const isDone = computed(() => props.round === null);

const title = computed(() =>
    isDone.value ? `${props.label} concluído` : roundTitle(props.label, props.round, props.totalRounds),
);

const restDisplay = computed(() => {
    const m = Math.floor(props.restSeconds / 60);
    const s = props.restSeconds % 60;
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
});
</script>

<template>
    <div class="flex flex-col gap-4">
        <BaseCard>
            <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-2.5">
                    <BaseBadge tone="accent">{{ label }}</BaseBadge>
                    <h2 class="truncate text-base font-bold text-text-primary [font-variant-numeric:tabular-nums]">
                        {{ title }}
                    </h2>
                </div>
                <Transition name="fade">
                    <span
                        v-if="exerciseTimerActive && !isDone"
                        class="shrink-0 font-mono text-sm font-bold text-orange-400"
                    >
                        {{ exerciseTimerDisplay }}
                    </span>
                </Transition>
            </div>
            <p v-if="!isDone" class="mt-2 text-xs text-text-tertiary">
                Faça os exercícios em sequência, sem descanso entre eles.
            </p>
        </BaseCard>

        <SupersetRoundSection
            v-for="section in sections"
            :key="section.exercise.id"
            :exercise="section.exercise"
            :set-number="section.setNumber"
            :total-sets="section.totalSets"
            :inputs="section.inputs"
            :round-set-done="section.roundSetDone"
            :done-sets="section.doneSets"
            :last-log="section.lastLog"
            :overload-suggestion="section.overloadSuggestion"
            :overload-status="overloadStatus"
            @adjust-weight="(n, delta) => $emit('adjust-weight', section.exercise, n, delta)"
            @adjust-reps="(n, delta) => $emit('adjust-reps', section.exercise, n, delta)"
            @set-weight="(n, value) => $emit('set-weight', section.exercise, n, value)"
            @set-reps="(n, value) => $emit('set-reps', section.exercise, n, value)"
            @edit-set="(n) => $emit('edit-set', section.exercise, n)"
            @finish-exercise="$emit('finish-exercise', section.exercise)"
            @apply-overload-suggestion="(payload) => $emit('apply-overload-suggestion', section.exercise, payload)"
            @dismiss-overload-suggestion="$emit('dismiss-overload-suggestion', section.exercise)"
        />

        <template v-if="!isDone">
            <p v-if="error" role="alert" class="text-center text-sm text-danger">{{ error }}</p>

            <CompleteSetButton
                :saving="saving"
                label="Concluir rodada"
                @click="$emit('complete-round')"
            />

            <div class="flex items-center justify-between px-1.5">
                <span class="text-[13px] text-text-tertiary">
                    Descanso <span class="text-text-numeric [font-variant-numeric:tabular-nums]">{{ restDisplay }}</span>
                </span>
                <button
                    v-if="!isLastBlock"
                    type="button"
                    @click="$emit('next-exercise')"
                    class="min-h-12 text-[13px] text-text-tertiary transition hover:text-accent-text-soft"
                >
                    Pular {{ label.toLowerCase() }}
                </button>
            </div>
        </template>

        <BlockDoneActions
            v-else
            :title="`${label} concluído!`"
            description="Todas as rodadas foram registradas."
            :is-last="isLastBlock"
            extra-label="+ Adicionar rodada extra"
            @next="$emit('next-exercise')"
            @finish-workout="$emit('finish-workout')"
            @add-extra="$emit('add-extra-round')"
        />
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
