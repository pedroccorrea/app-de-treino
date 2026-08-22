<script setup>
import { computed } from 'vue';

const props = defineProps({
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
});

const weeklyTotalSets = computed(() =>
    props.weeklyVolume.reduce((total, entry) => total + entry.sets, 0),
);

function formatWeight(weight) {
    return Number.isInteger(weight) ? `${weight}kg` : `${weight.toFixed(1)}kg`;
}
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2">
        <div
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
        >
            <div class="flex items-center gap-3">
                <span
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-orange-500/10 text-2xl"
                >
                    🔥
                </span>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {{ streak }}
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            {{ streak === 1 ? 'dia' : 'dias' }}
                        </span>
                    </p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                        Sequência atual de treinos
                    </p>
                </div>
            </div>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
        >
            <div class="flex items-center gap-3">
                <span
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-violet-500/10 text-2xl"
                >
                    📊
                </span>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {{ weeklyTotalSets }}
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            séries
                        </span>
                    </p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                        Volume total nesta semana
                    </p>
                </div>
            </div>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:col-span-2"
        >
            <h3 class="flex items-center gap-2 text-sm font-bold text-gray-900 dark:text-gray-100">
                <span class="text-lg">🏆</span>
                Recordes Pessoais
            </h3>

            <ul
                v-if="personalRecords.length"
                class="mt-3 divide-y divide-gray-100 dark:divide-gray-700"
            >
                <li
                    v-for="record in personalRecords"
                    :key="record.exercise_id"
                    class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
                >
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ record.exercise_name }}
                    </span>
                    <div class="text-right">
                        <span class="text-sm font-bold text-violet-600 dark:text-violet-400">
                            {{ formatWeight(record.weight) }} × {{ record.reps }}
                        </span>
                        <p
                            v-if="record.achieved_at"
                            class="text-xs text-gray-500 dark:text-gray-400"
                        >
                            {{ record.achieved_at }}
                        </p>
                    </div>
                </li>
            </ul>

            <p v-else class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                Registre séries com carga para começar a acumular recordes pessoais.
            </p>
        </div>
    </div>
</template>
