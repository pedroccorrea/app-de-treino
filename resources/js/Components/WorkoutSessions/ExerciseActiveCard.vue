<script setup>
import { computed } from 'vue';
import CompleteSetButton from '@/Components/WorkoutSessions/CompleteSetButton.vue';
import LoadRepsHero from '@/Components/WorkoutSessions/LoadRepsHero.vue';
import LoadRepsStepper from '@/Components/WorkoutSessions/LoadRepsStepper.vue';
import OverloadSuggestionCard from '@/Components/WorkoutSessions/OverloadSuggestionCard.vue';
import SetWaveProgress from '@/Components/WorkoutSessions/SetWaveProgress.vue';

const props = defineProps({
    exercise: {
        type: Object,
        required: true,
    },
    overloadSuggestion: {
        type: Object,
        default: null,
    },
    lastLog: {
        type: Object,
        default: null,
    },
    // setInputs[setNumber] = { weight, reps }. Read here for display; only the
    // parent page mutates it, in response to the adjust-weight/adjust-reps
    // events this component emits.
    setInputs: {
        type: Object,
        required: true,
    },
    sets: {
        // Each item: { number, state } where state is waiting|running|done
        type: Array,
        required: true,
    },
    savingSet: {
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
    isExerciseDone: {
        type: Boolean,
        default: false,
    },
    isLastExercise: {
        type: Boolean,
        default: false,
    },
    canAddExtraSet: {
        type: Boolean,
        default: false,
    },
});

defineEmits([
    'adjust-weight',
    'adjust-reps',
    'complete-set',
    'next-exercise',
    'finish-workout',
    'add-extra-set',
    'apply-overload-suggestion',
    'dismiss-overload-suggestion',
]);

const fmt = (n) => Number(n).toLocaleString('pt-BR', { maximumFractionDigits: 2 });

const runningSetNumber = computed(() => props.sets.find((s) => s.state === 'running')?.number ?? null);

const lastSetText = computed(() =>
    props.lastLog ? `${fmt(props.lastLog.weight)}kg × ${props.lastLog.reps}` : null,
);

const deltaText = computed(() => {
    if (!props.lastLog || runningSetNumber.value === null) return null;

    const currentWeight = parseFloat(props.setInputs[runningSetNumber.value]?.weight);
    const lastWeight = parseFloat(props.lastLog.weight);
    if (Number.isNaN(currentWeight) || Number.isNaN(lastWeight)) return null;

    const diff = currentWeight - lastWeight;
    if (diff === 0) return null;

    return `${diff > 0 ? '+' : '-'}${fmt(Math.abs(diff))} kg`;
});

const restDisplay = computed(() => {
    const total = props.exercise.rest_seconds ?? 60;
    const m = Math.floor(total / 60);
    const s = total % 60;
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
});
</script>

<template>
    <div>
        <!-- Exercise meta card -->
        <div class="rounded-2xl bg-gray-900 border border-gray-800 p-4 mb-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <h2 class="text-xl font-bold text-white truncate">{{ exercise.name }}</h2>
                    <p class="mt-0.5 text-sm text-gray-400">{{ exercise.primary_muscle }}</p>
                </div>
            </div>

            <div
                v-if="lastLog"
                class="mt-3 flex items-center gap-2 rounded-xl bg-gray-800/70 px-3 py-2"
            >
                <span class="text-base">📊</span>
                <div>
                    <p class="text-xs text-gray-500">Último treino</p>
                    <p class="text-sm font-bold text-emerald-400">
                        {{ lastLog.summary }}
                    </p>
                </div>
            </div>

            <div v-if="exercise.target_sets" class="mt-2">
                <p class="text-xs text-gray-500">
                    Meta:
                    <span class="font-semibold text-violet-400">
                        {{ exercise.target_sets }} séries
                        <template v-if="exercise.target_reps">
                            × {{ exercise.target_reps }} reps
                        </template>
                    </span>
                </p>
            </div>
        </div>

        <!-- AI-backed load suggestion, before/while executing this exercise -->
        <OverloadSuggestionCard
            v-if="overloadSuggestion && !isExerciseDone"
            class="mb-4"
            :suggested-load="overloadSuggestion.suggested_load"
            :suggested-reps="overloadSuggestion.suggested_reps"
            :previous-load="lastLog?.weight ?? null"
            :previous-reps="lastLog?.reps ?? null"
            :rationale="overloadSuggestion.rationale"
            @apply="$emit('apply-overload-suggestion', $event)"
            @dismiss="$emit('dismiss-overload-suggestion')"
        />

        <!-- ─── Sets ─────────────────────────────────────────────── -->
        <div class="space-y-3">
            <template v-for="set in sets" :key="set.number">

                <!-- ── DONE: Saved set (compact green row) ── -->
                <div
                    v-if="set.state === 'done'"
                    class="flex items-center gap-3 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3"
                >
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-500 text-sm font-bold text-white flex-shrink-0">
                        ✓
                    </span>
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-emerald-400">Série {{ set.number }}</p>
                        <p class="text-xs text-gray-500">
                            {{ setInputs[set.number]?.weight || '—' }}kg
                            × {{ setInputs[set.number]?.reps || '—' }} reps
                        </p>
                    </div>
                    <svg class="h-5 w-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                </div>

                <!-- ── RUNNING: Active set — load/reps hero, steppers, complete ── -->
                <div v-else-if="set.state === 'running'" class="flex flex-col gap-6 pt-2">
                    <div class="flex items-center justify-between">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-violet-600 text-sm font-bold text-white">
                            {{ set.number }}
                        </span>
                        <Transition name="fade">
                            <span
                                v-if="exerciseTimerActive"
                                class="font-mono text-sm font-bold text-orange-400"
                            >
                                {{ exerciseTimerDisplay }}
                            </span>
                        </Transition>
                    </div>

                    <SetWaveProgress :set-number="set.number" :total-sets="sets.length" />

                    <LoadRepsHero
                        :load="setInputs[set.number]?.weight || 0"
                        :reps="setInputs[set.number]?.reps || 0"
                        :last-text="lastSetText"
                        :delta-text="deltaText"
                    />

                    <div class="flex gap-2.5">
                        <LoadRepsStepper
                            label="Carga · 2,5"
                            @decrement="$emit('adjust-weight', set.number, -2.5)"
                            @increment="$emit('adjust-weight', set.number, 2.5)"
                        />
                        <LoadRepsStepper
                            label="Reps · 1"
                            @decrement="$emit('adjust-reps', set.number, -1)"
                            @increment="$emit('adjust-reps', set.number, 1)"
                        />
                    </div>

                    <CompleteSetButton
                        :saving="savingSet === `${exercise.id}-${set.number}`"
                        :label="`Concluir Série ${set.number}`"
                        @click="$emit('complete-set', set.number)"
                    />

                    <div class="flex items-center justify-between px-1.5">
                        <span class="text-[13px] text-[#6E6E7E]">
                            Descanso <span class="text-[#C9C9D4] [font-variant-numeric:tabular-nums]">{{ restDisplay }}</span>
                        </span>
                        <button
                            v-if="!isLastExercise"
                            type="button"
                            @click="$emit('next-exercise')"
                            class="text-[13px] text-[#6E6E7E] transition hover:text-[#C4B5FD]"
                        >
                            Pular exercício
                        </button>
                    </div>
                </div>

                <!-- Sets in "waiting" state are intentionally not rendered: the next
                     set only becomes visible (and running) after rest ends. -->

            </template>
        </div>

        <!-- Exercise done feedback + actions -->
        <div v-if="isExerciseDone" class="mt-4 space-y-3">
            <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-center">
                <p class="text-2xl">🎉</p>
                <p class="mt-1 font-bold text-emerald-400">Exercício concluído!</p>
                <p class="text-xs text-gray-500 mt-0.5">Todas as séries foram registradas.</p>
            </div>

            <button
                v-if="!isLastExercise"
                @click="$emit('next-exercise')"
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
                @click="$emit('add-extra-set')"
                class="flex w-full items-center justify-center gap-2 rounded-2xl border border-dashed border-gray-700 py-2.5 text-sm text-gray-400 transition hover:border-violet-500/50 hover:text-violet-400"
            >
                + Adicionar série extra
            </button>
        </div>

        <button
            v-else-if="canAddExtraSet"
            @click="$emit('add-extra-set')"
            class="mt-4 flex w-full items-center justify-center gap-2 rounded-2xl border border-dashed border-gray-700 py-2.5 text-sm text-gray-400 transition hover:border-violet-500/50 hover:text-violet-400"
        >
            + Adicionar série extra
        </button>
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
