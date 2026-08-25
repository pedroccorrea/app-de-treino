<script setup>
defineProps({
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
    // setInputs[setNumber] = { weight, reps }. Mutated directly by this
    // component's inputs, the same way a v-model target owned by the
    // parent page would be (see ScanWorkoutModal's `form` prop).
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
]);
</script>

<template>
    <div>
        <!-- Exercise meta card -->
        <div class="rounded-2xl bg-gray-900 border border-gray-800 p-4 mb-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <h2 class="text-xl font-bold text-white truncate">{{ exercise.name }}</h2>
                    <p class="mt-0.5 text-sm text-gray-400">{{ exercise.primary_muscle }}</p>
                    <p
                        v-if="overloadSuggestion"
                        class="mt-1.5 text-xs font-medium text-violet-400"
                    >
                        💡 Dica da IA: {{ overloadSuggestion.current_load }} →
                        {{ overloadSuggestion.suggested_load }}
                        (Meta: {{ overloadSuggestion.suggested_reps }})
                    </p>
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

                <!-- ── RUNNING: Active set with inputs ── -->
                <div
                    v-else-if="set.state === 'running'"
                    class="rounded-2xl border border-violet-500/60 bg-gray-900 p-4 ring-1 ring-violet-500/20"
                >
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-violet-600 text-sm font-bold text-white">
                                {{ set.number }}
                            </span>
                            <p class="text-sm font-semibold text-violet-300">
                                Série {{ set.number }}/{{ sets.length }} — Executando
                            </p>
                        </div>
                        <Transition name="fade">
                            <span
                                v-if="exerciseTimerActive"
                                class="font-mono text-sm font-bold text-orange-400"
                            >
                                {{ exerciseTimerDisplay }}
                            </span>
                        </Transition>
                    </div>

                    <div class="mb-3">
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Peso (kg)
                        </label>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="$emit('adjust-weight', set.number, -2.5)"
                                class="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-800 text-xl font-bold text-gray-300 transition hover:bg-gray-700 hover:text-white active:scale-95"
                            >−</button>
                            <input
                                v-model="setInputs[set.number].weight"
                                type="number"
                                min="0"
                                step="0.5"
                                placeholder="0"
                                class="flex-1 rounded-xl border-gray-700 bg-gray-800 py-3 text-center text-2xl font-bold text-white focus:border-violet-500 focus:ring-violet-500"
                            />
                            <button
                                type="button"
                                @click="$emit('adjust-weight', set.number, 2.5)"
                                class="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-800 text-xl font-bold text-gray-300 transition hover:bg-gray-700 hover:text-white active:scale-95"
                            >+</button>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Repetições
                        </label>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="$emit('adjust-reps', set.number, -1)"
                                class="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-800 text-xl font-bold text-gray-300 transition hover:bg-gray-700 hover:text-white active:scale-95"
                            >−</button>
                            <input
                                v-model="setInputs[set.number].reps"
                                type="number"
                                min="1"
                                placeholder="0"
                                class="flex-1 rounded-xl border-gray-700 bg-gray-800 py-3 text-center text-2xl font-bold text-white focus:border-violet-500 focus:ring-violet-500"
                            />
                            <button
                                type="button"
                                @click="$emit('adjust-reps', set.number, 1)"
                                class="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-800 text-xl font-bold text-gray-300 transition hover:bg-gray-700 hover:text-white active:scale-95"
                            >+</button>
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="$emit('complete-set', set.number)"
                        :disabled="savingSet === `${exercise.id}-${set.number}`"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-violet-600 to-violet-500 py-4 text-base font-bold text-white shadow-lg shadow-violet-500/30 transition hover:from-violet-700 hover:to-violet-600 active:scale-[0.98] disabled:opacity-60"
                    >
                        <span v-if="savingSet === `${exercise.id}-${set.number}`" class="flex items-center gap-2">
                            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Salvando...
                        </span>
                        <span v-else>✓ Concluir Série {{ set.number }}</span>
                    </button>
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
