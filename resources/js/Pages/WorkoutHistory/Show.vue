<script setup>
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseCard from '@/Components/UI/BaseCard.vue';
import SectionLabel from '@/Components/UI/SectionLabel.vue';
import HistoryEmptyState from '@/Components/WorkoutHistory/HistoryEmptyState.vue';
import HistoryExerciseCard from '@/Components/WorkoutHistory/HistoryExerciseCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    DELETED_WORKOUT_LABEL,
    formatDuration,
    formatSessionDate,
    formatSessionTime,
    formatVolume,
} from '@/Utils/historyFormat';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    // { id, workout_name, completed_at, started_at, duration_minutes,
    //   exercise_count, set_count, volume, exercises: [...] }
    // null apenas na versão montada offline pelo Service Worker.
    session: {
        type: Object,
        default: null,
    },
    offline: {
        type: Boolean,
        default: false,
    },
});

// Mesmo padrão de `Exercises/History.vue`: a origem viaja em ?return_to=...
const returnTo = new URLSearchParams(window.location.search).get('return_to');
const backHref = computed(() => returnTo || route('history.index'));

const title = computed(() => props.session?.workout_name ?? DELETED_WORKOUT_LABEL);

const stats = computed(() => {
    if (!props.session) return [];

    return [
        { label: 'Exercícios', value: props.session.exercise_count },
        { label: 'Séries', value: props.session.set_count },
        { label: 'Volume', value: formatVolume(props.session.volume) },
        { label: 'Duração', value: formatDuration(props.session.duration_minutes) ?? '—' },
    ];
});

// Ao abrir a evolução de um exercício, o "Voltar" de lá traz o usuário de
// volta exatamente para este detalhe.
const exerciseHistoryHref = (exercise) =>
    route('exercises.history', {
        exercise: exercise.exercise_id,
        return_to: window.location.pathname + window.location.search,
    });
</script>

<template>
    <Head :title="`Histórico · ${title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                <Link
                    :href="backHref"
                    class="inline-flex min-h-[48px] items-center gap-1 self-start text-sm font-medium text-text-secondary transition hover:text-accent-text-soft sm:self-auto"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Voltar
                </Link>

                <div v-if="session" class="min-w-0">
                    <h2
                        class="truncate text-xl font-semibold leading-tight"
                        :class="session.workout_name ? 'text-text-primary' : 'italic text-text-secondary'"
                    >
                        {{ title }}
                    </h2>
                    <p class="mt-0.5 text-sm text-text-secondary">
                        {{ formatSessionDate(session.completed_at) }} · {{ formatSessionTime(session.completed_at) }}
                    </p>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8">
                <HistoryEmptyState
                    v-if="!session || offline"
                    title="Treino indisponível offline"
                    message="Conecte-se à internet para ver os detalhes deste treino."
                >
                    <BaseButton variant="secondary" @click="router.visit(route('history.index'))">Voltar ao histórico</BaseButton>
                </HistoryEmptyState>

                <template v-else>
                    <BaseCard>
                        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <div v-for="stat in stats" :key="stat.label">
                                <dt><SectionLabel>{{ stat.label }}</SectionLabel></dt>
                                <dd class="mt-1 text-lg font-medium text-text-primary [font-variant-numeric:tabular-nums]">
                                    {{ stat.value }}
                                </dd>
                            </div>
                        </dl>
                    </BaseCard>

                    <div>
                        <SectionLabel>Exercícios</SectionLabel>

                        <p v-if="session.exercises.length === 0" class="mt-3 text-sm text-text-secondary">
                            Nenhuma série registrada neste treino.
                        </p>

                        <ol v-else class="mt-3 space-y-3">
                            <li v-for="exercise in session.exercises" :key="exercise.exercise_id">
                                <HistoryExerciseCard
                                    :exercise="exercise"
                                    :history-href="exerciseHistoryHref(exercise)"
                                />
                            </li>
                        </ol>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
