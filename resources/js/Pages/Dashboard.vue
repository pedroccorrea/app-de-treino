<script setup>
import ActiveWorkoutsList from '@/Components/Dashboard/ActiveWorkoutsList.vue';
import RestDayCard from '@/Components/Dashboard/RestDayCard.vue';
import StreakCard from '@/Components/Dashboard/StreakCard.vue';
import TodayWorkoutHero from '@/Components/Dashboard/TodayWorkoutHero.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    todayWorkout: {
        type: Object,
        default: null,
    },
    streak: {
        type: Number,
        default: 0,
    },
    activeWorkouts: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-text-primary">
                Dashboard
            </h2>
        </template>

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8">
                <TodayWorkoutHero v-if="todayWorkout" :workout="todayWorkout" />
                <RestDayCard v-else :active-workouts="activeWorkouts" />

                <StreakCard :streak="streak" />

                <ActiveWorkoutsList :workouts="activeWorkouts" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
