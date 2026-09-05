<script setup>
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseCard from '@/Components/UI/BaseCard.vue';

// Painel de sessão 100% local, usado quando o usuário inicia um treino sem
// conexão com a internet. Componente "burro": não conhece a fila de
// sincronização nem o IndexedDB — só recebe o estado da sessão via props e
// emite eventos, que a Página (Show.vue / Index.vue) traduz em chamadas ao
// composable `useOfflineWorkoutSession` (ver `.ai/rules/vue-inertia-architecture.md`).
const props = defineProps({
    workoutName: { type: String, required: true },
    exercises: { type: Array, required: true }, // [{ id, name }]
    currentIndex: { type: Number, required: true },
    currentExercise: { type: Object, default: null }, // { id, name, primary_muscle }
    setInputs: { type: Object, required: true }, // { [setNumber]: { weight, reps } }
    sets: { type: Array, required: true }, // [{ number, saved }]
    doneSetsCount: { type: Number, required: true },
    finished: { type: Boolean, default: false },
    finishing: { type: Boolean, default: false },
});

defineEmits(['go-to-exercise', 'update-weight', 'update-reps', 'save-set', 'finish', 'close']);
</script>

<template>
    <div class="fixed inset-0 z-50 flex flex-col bg-surface-base text-text-primary">
        <header class="flex items-center justify-between border-b border-border-subtle px-4 py-3">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-semibold">{{ workoutName }}</h2>
                    <BaseBadge tone="warning">MODO OFFLINE</BaseBadge>
                </div>
                <p class="mt-0.5 text-xs text-text-secondary">
                    {{ doneSetsCount }} série(s) registrada(s) neste dispositivo
                </p>
            </div>
            <button
                type="button"
                class="rounded-radius-sm p-2 text-text-secondary transition hover:bg-surface-overlay hover:text-text-primary"
                @click="$emit('close')"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </header>

        <div v-if="!finished" class="flex-1 overflow-y-auto px-4 py-5">
            <p class="mb-4 rounded-radius-md border border-warning/40 bg-warning/10 px-3 py-2 text-xs text-warning">
                Sem conexão com a internet. Suas séries estão sendo salvas neste dispositivo e serão
                enviadas automaticamente para o servidor assim que a internet voltar.
            </p>

            <!-- Navegação entre exercícios -->
            <div class="mb-4 flex gap-2 overflow-x-auto pb-1">
                <button
                    v-for="(exercise, index) in exercises"
                    :key="exercise.id"
                    type="button"
                    class="shrink-0 rounded-radius-full border px-3 py-1.5 text-xs font-medium transition"
                    :class="index === currentIndex
                        ? 'border-accent bg-accent-muted text-accent-text-strong'
                        : 'border-border-subtle text-text-secondary hover:border-border-accent'"
                    @click="$emit('go-to-exercise', index)"
                >
                    {{ exercise.name }}
                </button>
            </div>

            <BaseCard v-if="currentExercise">
                <h3 class="text-lg font-bold">{{ currentExercise.name }}</h3>
                <p class="mb-4 text-xs text-text-secondary">{{ currentExercise.primary_muscle }}</p>

                <div class="space-y-3">
                    <div
                        v-for="set in sets"
                        :key="set.number"
                        class="flex items-center gap-3 rounded-radius-md border border-border-subtle p-3"
                        :class="set.saved ? 'opacity-60' : ''"
                    >
                        <span class="w-6 shrink-0 text-sm font-bold text-text-secondary">{{ set.number }}</span>

                        <div class="flex flex-1 items-center gap-2">
                            <input
                                type="number"
                                step="0.5"
                                min="0"
                                :value="setInputs[set.number]?.weight"
                                :disabled="set.saved"
                                class="w-20 rounded-radius-sm border border-border-subtle bg-surface-overlay px-2 py-1.5 text-center text-sm text-text-primary"
                                placeholder="kg"
                                @input="$emit('update-weight', set.number, $event.target.value)"
                            />
                            <span class="text-xs text-text-tertiary">kg ×</span>
                            <input
                                type="number"
                                min="1"
                                :value="setInputs[set.number]?.reps"
                                :disabled="set.saved"
                                class="w-16 rounded-radius-sm border border-border-subtle bg-surface-overlay px-2 py-1.5 text-center text-sm text-text-primary"
                                placeholder="reps"
                                @input="$emit('update-reps', set.number, $event.target.value)"
                            />
                            <span class="text-xs text-text-tertiary">reps</span>
                        </div>

                        <BaseButton
                            v-if="!set.saved"
                            size="default"
                            variant="secondary"
                            @click="$emit('save-set', set.number)"
                        >
                            Salvar
                        </BaseButton>
                        <span v-else class="text-xs font-semibold text-success">✓ Salva</span>
                    </div>
                </div>
            </BaseCard>
        </div>

        <div v-else class="flex flex-1 flex-col items-center justify-center gap-3 px-6 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-success/10 text-3xl">✓</div>
            <h3 class="text-lg font-bold">Treino salvo offline!</h3>
            <p class="max-w-xs text-sm text-text-secondary">
                Assim que este dispositivo se reconectar à internet, o treino será sincronizado automaticamente.
            </p>
            <BaseButton variant="primary" class="mt-2" @click="$emit('close')">Concluir</BaseButton>
        </div>

        <footer v-if="!finished" class="border-t border-border-subtle px-4 py-3">
            <BaseButton variant="primary" class="w-full" :disabled="finishing" @click="$emit('finish')">
                {{ finishing ? 'Finalizando...' : 'Finalizar Treino (offline)' }}
            </BaseButton>
        </footer>
    </div>
</template>
