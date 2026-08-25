<script setup>
defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    exercises: {
        // Each item: { id, name, primary_muscle, done, doneCount, totalSets }
        type: Array,
        required: true,
    },
    currentIndex: {
        type: Number,
        required: true,
    },
});

defineEmits(['close', 'select']);
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="show"
                class="fixed inset-0 z-[60] flex items-end justify-center bg-black/70 backdrop-blur-sm"
                @click.self="$emit('close')"
            >
                <Transition name="slide-up">
                    <div
                        v-if="show"
                        class="w-full max-w-lg rounded-t-3xl bg-gray-900 border-t border-gray-700 p-5 max-h-[70vh] overflow-y-auto"
                    >
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-bold text-white">Visão Geral do Treino</h3>
                            <button @click="$emit('close')" class="text-gray-400 hover:text-white transition">✕</button>
                        </div>
                        <ul class="space-y-2">
                            <li
                                v-for="(ex, idx) in exercises"
                                :key="ex.id"
                                @click="$emit('select', idx)"
                                :class="[
                                    'flex items-center gap-3 rounded-2xl border p-3 cursor-pointer transition',
                                    idx === currentIndex
                                        ? 'border-violet-500 bg-violet-500/10'
                                        : ex.done
                                          ? 'border-emerald-500/30 bg-emerald-500/10'
                                          : 'border-gray-800 hover:border-gray-700',
                                ]"
                            >
                                <span :class="[
                                    'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                                    ex.done
                                        ? 'bg-emerald-500 text-white'
                                        : idx === currentIndex
                                          ? 'bg-violet-600 text-white'
                                          : 'bg-gray-800 text-gray-400',
                                ]">
                                    {{ ex.done ? '✓' : (idx + 1) }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-white truncate">{{ ex.name }}</p>
                                    <p class="text-xs text-gray-500">{{ ex.primary_muscle }}</p>
                                </div>
                                <p class="text-xs text-gray-500 shrink-0">
                                    {{ ex.doneCount }}/{{ ex.totalSets }}
                                </p>
                            </li>
                        </ul>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.slide-up-enter-active,
.slide-up-leave-active {
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.slide-up-enter-from,
.slide-up-leave-to {
    opacity: 0;
    transform: translateY(20px);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
