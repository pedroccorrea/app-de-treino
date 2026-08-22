<script setup>
import { computed } from 'vue';

const props = defineProps({
    alert: {
        type: Object,
        required: true,
    },
});

const isWarning = computed(() => props.alert.status === 'warning');

function formatMuscleName(rawName) {
    return rawName
        .toLowerCase()
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}
</script>

<template>
    <div
        class="overflow-hidden rounded-2xl border p-4"
        :class="
            isWarning
                ? 'border-orange-300 bg-orange-50 dark:border-orange-500/30 dark:bg-orange-500/5'
                : 'border-emerald-300 bg-emerald-50 dark:border-emerald-500/30 dark:bg-emerald-500/5'
        "
    >
        <div class="flex items-start gap-3">
            <span class="text-xl">{{ isWarning ? '⚠️' : '✅' }}</span>
            <div class="min-w-0 flex-1">
                <p
                    class="text-sm font-bold"
                    :class="
                        isWarning
                            ? 'text-orange-700 dark:text-orange-400'
                            : 'text-emerald-700 dark:text-emerald-400'
                    "
                >
                    {{ isWarning ? 'Alerta de Assimetria Muscular' : 'Equilíbrio Muscular em Dia' }}
                </p>
                <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                    {{ alert.summary }}
                </p>

                <div
                    v-if="alert.neglected_muscles?.length"
                    class="mt-3 flex flex-wrap items-center gap-1.5"
                >
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                        Negligenciados:
                    </span>
                    <span
                        v-for="muscle in alert.neglected_muscles"
                        :key="muscle"
                        class="rounded-lg bg-orange-500/10 px-2 py-0.5 text-xs font-medium text-orange-700 dark:text-orange-400"
                    >
                        {{ formatMuscleName(muscle) }}
                    </span>
                </div>

                <div
                    v-if="alert.dominant_muscles?.length"
                    class="mt-2 flex flex-wrap items-center gap-1.5"
                >
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                        Dominantes:
                    </span>
                    <span
                        v-for="muscle in alert.dominant_muscles"
                        :key="muscle"
                        class="rounded-lg bg-violet-500/10 px-2 py-0.5 text-xs font-medium text-violet-600 dark:text-violet-400"
                    >
                        {{ formatMuscleName(muscle) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
