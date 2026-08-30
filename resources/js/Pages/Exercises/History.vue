<script setup>
import BaseCard from '@/Components/UI/BaseCard.vue';
import SectionLabel from '@/Components/UI/SectionLabel.vue';
import ExerciseHistoryChart from '@/Components/Exercises/ExerciseHistoryChart.vue';
import RangeFilter from '@/Components/Exercises/RangeFilter.vue';
import SessionHistoryList from '@/Components/Exercises/SessionHistoryList.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    exercise: {
        type: Object,
        required: true,
    },
    range: {
        type: String,
        required: true,
    },
    ranges: {
        type: Array,
        required: true,
    },
    sessions: {
        type: Array,
        required: true,
    },
    volumeSeries: {
        type: Array,
        required: true,
    },
    personalRecord: {
        type: Object,
        default: null,
    },
});

// Same pattern used across the app (see Workouts/Show.vue): the origin page
// is preserved via ?return_to=... so the back button (and the range filter,
// which re-navigates this same page) can honor it instead of a hardcoded link.
const returnTo = new URLSearchParams(window.location.search).get('return_to');

const formatRecordDate = (isoDate) =>
    new Date(`${isoDate}T00:00:00`).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });

const fmt = (n) => Number(n).toLocaleString('pt-BR', { maximumFractionDigits: 2 });
</script>

<template>
    <Head :title="`Histórico · ${exercise.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                <Link
                    :href="returnTo || route('dashboard')"
                    class="inline-flex items-center gap-1 text-sm font-medium text-text-secondary transition hover:text-accent-text-soft"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Voltar
                </Link>

                <div>
                    <h2 class="text-xl font-semibold leading-tight text-text-primary">
                        {{ exercise.name }}
                    </h2>
                    <p class="mt-0.5 text-sm text-text-secondary">{{ exercise.primary_muscle }}</p>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8">
                <!-- Personal record, em destaque -->
                <BaseCard v-if="personalRecord">
                    <SectionLabel tone="accent">Recorde pessoal</SectionLabel>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-[52px] font-medium leading-[0.86] tracking-[-0.045em] text-text-primary [font-variant-numeric:tabular-nums]">
                            {{ fmt(personalRecord.weight) }}
                        </span>
                        <span class="text-lg font-medium text-text-secondary">kg</span>
                    </div>
                    <p class="mt-1 text-xs text-text-tertiary">
                        Registrado em {{ formatRecordDate(personalRecord.date) }}
                    </p>
                </BaseCard>
                <BaseCard v-else>
                    <SectionLabel tone="secondary">Recorde pessoal</SectionLabel>
                    <p class="mt-2 text-sm text-text-secondary">
                        Ainda não há cargas registradas para este exercício.
                    </p>
                </BaseCard>

                <!-- Filtro de período -->
                <div class="flex justify-center">
                    <RangeFilter
                        :exercise-id="exercise.id"
                        :ranges="ranges"
                        :current="range"
                        :return-to="returnTo"
                    />
                </div>

                <!-- Curva de progressão de volume -->
                <BaseCard>
                    <SectionLabel tone="secondary">Progressão de volume</SectionLabel>
                    <div class="mt-3">
                        <ExerciseHistoryChart :series="volumeSeries" />
                    </div>
                </BaseCard>

                <!-- Sessões passadas -->
                <div>
                    <SectionLabel tone="secondary">Sessões</SectionLabel>
                    <div class="mt-3">
                        <SessionHistoryList :sessions="sessions" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
