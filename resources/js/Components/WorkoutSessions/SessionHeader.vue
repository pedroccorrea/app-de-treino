<script setup>
defineProps({
    workoutName: {
        type: String,
        required: true,
    },
    elapsedDisplay: {
        type: String,
        required: true,
    },
    totalDoneSets: {
        type: Number,
        default: 0,
    },
    totalPlannedSets: {
        type: Number,
        default: 0,
    },
    progressPercent: {
        type: Number,
        default: 0,
    },
});

defineEmits(['open-drawer']);
</script>

<template>
    <header class="flex-shrink-0 border-b border-gray-800 bg-gray-900/80 backdrop-blur-sm px-4 py-3">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="relative flex h-2 w-2 flex-shrink-0">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-violet-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-violet-500"></span>
                    </span>
                    <p class="truncate text-sm font-bold text-white">{{ workoutName }}</p>
                </div>
                <div class="mt-0.5 flex items-center gap-3">
                    <p class="text-xs font-mono text-violet-400">⏱ {{ elapsedDisplay }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button
                    @click="$emit('open-drawer')"
                    class="flex items-center gap-1.5 rounded-lg bg-gray-800 px-3 py-1.5 text-xs font-semibold text-gray-300 transition hover:bg-gray-700 hover:text-white"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    Lista
                </button>
                <div class="text-right">
                    <p class="text-xs text-gray-400">{{ totalDoneSets }}/{{ totalPlannedSets }} séries</p>
                    <div class="mt-1 h-1.5 w-20 overflow-hidden rounded-full bg-gray-700">
                        <div
                            class="h-full rounded-full bg-gradient-to-r from-violet-500 to-emerald-400 transition-all duration-500"
                            :style="{ width: progressPercent + '%' }"
                        />
                    </div>
                </div>
            </div>
        </div>
    </header>
</template>
