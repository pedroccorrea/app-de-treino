<script setup>
import { computed } from 'vue';

const props = defineProps({
    activity: {
        type: Array,
        default: () => [],
    },
});

// The backend always returns full weeks starting on Monday, so chunks of 7
// map cleanly onto calendar columns (oldest week first, left to right).
const weeks = computed(() => {
    const chunks = [];
    for (let i = 0; i < props.activity.length; i += 7) {
        chunks.push(props.activity.slice(i, i + 7));
    }
    return chunks;
});

function intensityClass(count) {
    if (count === 0) return 'bg-gray-100 dark:bg-gray-700';
    if (count === 1) return 'bg-violet-300 dark:bg-violet-800';
    if (count === 2) return 'bg-violet-500 dark:bg-violet-600';
    return 'bg-violet-700 dark:bg-violet-400';
}

function formatDate(dateString) {
    return new Date(`${dateString}T00:00:00`).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
    });
}
</script>

<template>
    <div
        class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
    >
        <h3 class="flex items-center gap-2 text-sm font-bold text-gray-900 dark:text-gray-100">
            <span class="text-lg">📅</span>
            Consistência de Treinos
        </h3>

        <div class="mt-4 overflow-x-auto">
            <div class="flex gap-1">
                <div
                    v-for="(week, weekIndex) in weeks"
                    :key="weekIndex"
                    class="flex flex-col gap-1"
                >
                    <div
                        v-for="day in week"
                        :key="day.date"
                        :class="intensityClass(day.count)"
                        :title="`${formatDate(day.date)} · ${day.count} ${day.count === 1 ? 'treino' : 'treinos'}`"
                        class="h-3 w-3 rounded-sm sm:h-3.5 sm:w-3.5"
                    />
                </div>
            </div>
        </div>

        <div class="mt-3 flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
            <span>Menos</span>
            <span class="h-3 w-3 rounded-sm bg-gray-100 dark:bg-gray-700" />
            <span class="h-3 w-3 rounded-sm bg-violet-300 dark:bg-violet-800" />
            <span class="h-3 w-3 rounded-sm bg-violet-500 dark:bg-violet-600" />
            <span class="h-3 w-3 rounded-sm bg-violet-700 dark:bg-violet-400" />
            <span>Mais</span>
        </div>
    </div>
</template>
