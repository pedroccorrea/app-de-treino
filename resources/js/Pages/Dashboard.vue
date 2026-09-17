<script setup>
import ActiveWorkoutsList from '@/Components/Dashboard/ActiveWorkoutsList.vue';
import RestDayCard from '@/Components/Dashboard/RestDayCard.vue';
import StreakCard from '@/Components/Dashboard/StreakCard.vue';
import TodayWorkoutHero from '@/Components/Dashboard/TodayWorkoutHero.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { getOfflineDashboardState } from '@/Utils/offlineStorage';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
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

// ─── Autoridade do IndexedDB em modo offline (Phase 36) ────────────────────
// As props acima podem ter vindo de uma navegação client-side do Inertia que
// nunca chegou a passar pelo mini-servidor do Service Worker (que já resolve
// esta mesma página a partir do IndexedDB quando intercepta a requisição —
// ver `buildDashboardProps` em `resources/pwa/sw.js`). Offline, recalcula o
// Card Hero e a lista de treinos ativos direto do IndexedDB local, para que
// o que aparece na tela seja sempre igual ao estado físico salvo no aparelho.
const isOnline = ref(typeof navigator === 'undefined' || navigator.onLine);
const offlineState = ref(null);

async function loadOfflineState() {
    offlineState.value = await getOfflineDashboardState();
}

function updateOnlineStatus() {
    isOnline.value = navigator.onLine;
    if (!isOnline.value) loadOfflineState();
}

onMounted(() => {
    if (!isOnline.value) loadOfflineState();
    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);
});

onUnmounted(() => {
    window.removeEventListener('online', updateOnlineStatus);
    window.removeEventListener('offline', updateOnlineStatus);
});

const displayTodayWorkout = computed(() =>
    !isOnline.value && offlineState.value ? offlineState.value.todayWorkout : props.todayWorkout,
);

const displayActiveWorkouts = computed(() =>
    !isOnline.value && offlineState.value ? offlineState.value.activeWorkouts : props.activeWorkouts,
);
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
                <TodayWorkoutHero v-if="displayTodayWorkout" :workout="displayTodayWorkout" />
                <RestDayCard v-else :active-workouts="displayActiveWorkouts" />

                <StreakCard :streak="streak" />

                <ActiveWorkoutsList :workouts="displayActiveWorkouts" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
