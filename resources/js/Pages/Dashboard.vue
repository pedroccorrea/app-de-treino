<script setup>
import ActivityHeatmap from '@/Components/Dashboard/ActivityHeatmap.vue';
import MetricCards from '@/Components/Dashboard/MetricCards.vue';
import MuscleBalanceAlert from '@/Components/Dashboard/MuscleBalanceAlert.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    streak: {
        type: Number,
        default: 0,
    },
    personalRecords: {
        type: Array,
        default: () => [],
    },
    weeklyVolume: {
        type: Array,
        default: () => [],
    },
    activityHeatmap: {
        type: Array,
        default: () => [],
    },
    muscleBalanceAlert: {
        type: Object,
        default: null,
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Dashboard
            </h2>
        </template>

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                <MuscleBalanceAlert v-if="muscleBalanceAlert" :alert="muscleBalanceAlert" />

                <MetricCards
                    :streak="streak"
                    :personal-records="personalRecords"
                    :weekly-volume="weeklyVolume"
                />

                <ActivityHeatmap :activity="activityHeatmap" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
