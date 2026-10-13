<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import LoadRepsHero from '@/Components/WorkoutSessions/LoadRepsHero.vue';
import LoadRepsStepper from '@/Components/WorkoutSessions/LoadRepsStepper.vue';
import OverloadSuggestionCard from '@/Components/WorkoutSessions/OverloadSuggestionCard.vue';
import { DEFAULT_LOAD_INCREMENT, stepLoad } from '@/Utils/loadSteps';

// Um exercício dentro do card da rodada de um bi-set/tri-set (Phase 53):
// nome, séries já feitas (tocar edita), sugestão da IA e carga/reps da série
// desta rodada com os mesmos +/− do card de exercício sozinho. Componente
// "burro": só exibe e emite; quem muda os valores é a página.
const props = defineProps({
    exercise: {
        type: Object,
        required: true,
    },
    // Número da série deste exercício na rodada atual (= número da rodada).
    // `null` quando o grupo já terminou: a seção mostra só as séries feitas.
    setNumber: {
        type: Number,
        default: null,
    },
    totalSets: {
        type: Number,
        default: 0,
    },
    // { weight, reps } da série desta rodada.
    inputs: {
        type: Object,
        default: null,
    },
    // A série desta rodada já foi registrada (ex.: a rodada falhou no meio
    // e este exercício foi salvo antes da falha).
    roundSetDone: {
        type: Boolean,
        default: false,
    },
    // Séries já concluídas: [{ number, weight, reps }].
    doneSets: {
        type: Array,
        default: () => [],
    },
    lastLog: {
        type: Object,
        default: null,
    },
    overloadSuggestion: {
        type: Object,
        default: null,
    },
    overloadStatus: {
        type: String,
        default: 'ready',
    },
});

const emit = defineEmits([
    'adjust-weight',
    'adjust-reps',
    'set-weight',
    'set-reps',
    // (setNumber) — toque numa série já concluída, para corrigi-la.
    'edit-set',
    'finish-exercise',
    'apply-overload-suggestion',
    'dismiss-overload-suggestion',
]);

const fmt = (n) => Number(n).toLocaleString('pt-BR', { maximumFractionDigits: 2 });

const isEditable = computed(() => props.setNumber !== null && !props.roundSetDone && props.inputs !== null);

const lastSetText = computed(() =>
    props.lastLog ? `${fmt(props.lastLog.weight)}kg × ${props.lastLog.reps}` : null,
);

// Mesmo passo de carga do card de exercício sozinho (segue o equipamento).
const currentWeight = computed(() => parseFloat(props.inputs?.weight) || 0);
const loadDeltaUp = computed(() => stepLoad(props.exercise.equipment, currentWeight.value, 1) - currentWeight.value);
const loadDeltaDown = computed(() => stepLoad(props.exercise.equipment, currentWeight.value, -1) - currentWeight.value);
const loadStepLabel = computed(() => `Carga · ${fmt(loadDeltaUp.value || DEFAULT_LOAD_INCREMENT)}`);

const historyUrl = computed(() =>
    route('exercises.history', {
        exercise: props.exercise.id,
        return_to: window.location.pathname + window.location.search,
    }),
);
</script>

<template>
    <section
        :aria-label="exercise.name"
        class="flex flex-col gap-4 rounded-radius-lg border border-border-subtle bg-surface-overlay/40 p-4"
    >
        <header class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <Link :href="historyUrl" class="group inline-flex max-w-full items-center gap-1.5">
                    <h3 class="truncate text-lg font-bold text-text-primary transition group-hover:text-accent-text-soft">{{ exercise.name }}</h3>
                    <svg class="h-4 w-4 shrink-0 text-text-tertiary transition group-hover:text-accent-text-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </Link>
                <p class="text-xs text-text-secondary">{{ exercise.primary_muscle }}</p>
            </div>
            <span
                v-if="setNumber !== null"
                class="shrink-0 pt-1 text-xs text-text-tertiary [font-variant-numeric:tabular-nums]"
            >
                Série {{ setNumber }} de {{ totalSets }}
            </span>
        </header>

        <!-- Séries já feitas deste exercício: tocar abre o editor (Phases 45/46). -->
        <div v-if="doneSets.length" class="flex flex-wrap gap-2">
            <button
                v-for="set in doneSets"
                :key="set.number"
                type="button"
                :aria-label="`Editar série ${set.number} de ${exercise.name}`"
                class="flex min-h-12 items-center gap-2 rounded-radius-md border border-emerald-500/30 bg-emerald-500/10 px-3 text-left transition hover:border-emerald-500/50 active:scale-[0.98]"
                @click="emit('edit-set', set.number)"
            >
                <span class="text-sm font-bold text-emerald-400">✓ {{ set.number }}</span>
                <span class="text-xs text-text-secondary [font-variant-numeric:tabular-nums]">
                    {{ set.weight || '—' }}kg × {{ set.reps || '—' }}
                </span>
                <svg class="h-3.5 w-3.5 text-text-tertiary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M4 20h4l10.5-10.5a2.5 2.5 0 00-3.536-3.536L4.5 16.5 4 20z" />
                </svg>
            </button>
        </div>

        <template v-if="isEditable">
            <!-- Sugestão da IA deste exercício (aplicar/dispensar como hoje). -->
            <p v-if="overloadStatus === 'loading' && !overloadSuggestion" class="text-xs text-text-tertiary">
                💡 Analisando histórico de cargas...
            </p>
            <OverloadSuggestionCard
                v-else-if="overloadSuggestion"
                :suggested-load="overloadSuggestion.suggested_load"
                :suggested-reps="overloadSuggestion.suggested_reps"
                :previous-load="overloadSuggestion.previous_load"
                :previous-reps="overloadSuggestion.previous_reps"
                :rationale="overloadSuggestion.rationale"
                :kind="overloadSuggestion.kind"
                :basis="overloadSuggestion.basis"
                @apply="emit('apply-overload-suggestion', $event)"
                @dismiss="emit('dismiss-overload-suggestion')"
            />

            <LoadRepsHero
                compact
                :load="inputs.weight || 0"
                :reps="inputs.reps || 0"
                :last-text="lastSetText"
                @update:load="emit('set-weight', setNumber, $event)"
                @update:reps="emit('set-reps', setNumber, $event)"
            />

            <div class="flex gap-2.5">
                <LoadRepsStepper
                    :label="loadStepLabel"
                    @decrement="emit('adjust-weight', setNumber, loadDeltaDown)"
                    @increment="emit('adjust-weight', setNumber, loadDeltaUp)"
                />
                <LoadRepsStepper
                    label="Reps · 1"
                    @decrement="emit('adjust-reps', setNumber, -1)"
                    @increment="emit('adjust-reps', setNumber, 1)"
                />
            </div>
        </template>

        <p v-else-if="setNumber !== null && roundSetDone" class="text-xs text-emerald-400">
            Série {{ setNumber }} já registrada nesta rodada.
        </p>

        <!-- Encerra só este exercício; as próximas rodadas seguem com os outros. -->
        <button
            v-if="setNumber !== null"
            type="button"
            class="flex min-h-12 w-full items-center justify-center rounded-radius-md border border-dashed border-accent-muted text-sm text-accent-label transition hover:border-border-accent hover:text-accent-text-soft"
            :aria-label="`Finalizar ${exercise.name}`"
            @click="emit('finish-exercise')"
        >
            Finalizar exercício
        </button>
    </section>
</template>
