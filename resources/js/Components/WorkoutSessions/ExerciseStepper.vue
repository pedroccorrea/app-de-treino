<script setup>
defineProps({
    exercises: {
        // Each item: { id, name, done }
        type: Array,
        required: true,
    },
    currentIndex: {
        type: Number,
        required: true,
    },
});

defineEmits(['navigate']);
</script>

<template>
    <div class="flex-shrink-0 border-b border-gray-800 bg-gray-900/60 px-4 py-3">
        <div class="flex items-center justify-between gap-2">
            <button
                @click="$emit('navigate', currentIndex - 1)"
                :disabled="currentIndex === 0"
                class="flex h-9 w-9 items-center justify-center rounded-xl bg-gray-800 text-gray-400 transition hover:bg-gray-700 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
            </button>

            <div class="flex flex-1 items-center justify-center gap-1.5 overflow-x-auto px-1">
                <button
                    v-for="(ex, idx) in exercises"
                    :key="ex.id"
                    @click="$emit('navigate', idx)"
                    :class="[
                        'h-2.5 rounded-full transition-all duration-300 flex-shrink-0',
                        idx === currentIndex
                            ? 'w-6 bg-violet-500'
                            : ex.done
                              ? 'w-2.5 bg-emerald-400'
                              : 'w-2.5 bg-gray-600 hover:bg-gray-500',
                    ]"
                    :title="ex.name"
                />
            </div>

            <button
                @click="$emit('navigate', currentIndex + 1)"
                :disabled="currentIndex === exercises.length - 1"
                class="flex h-9 w-9 items-center justify-center rounded-xl bg-gray-800 text-gray-400 transition hover:bg-gray-700 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>

        <p class="mt-2 text-center text-sm font-semibold text-white truncate px-10">
            {{ exercises[currentIndex]?.name }}
        </p>
        <p class="mt-0.5 text-center text-xs text-gray-500">
            Exercício {{ currentIndex + 1 }} de {{ exercises.length }}
        </p>
    </div>
</template>
