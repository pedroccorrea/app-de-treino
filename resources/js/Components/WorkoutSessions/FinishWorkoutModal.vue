<script setup>
defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    finishing: {
        type: Boolean,
        default: false,
    },
    elapsedDisplay: {
        type: String,
        default: '00:00',
    },
    totalDoneSets: {
        type: Number,
        default: 0,
    },
    volumeDisplay: {
        type: String,
        default: '0kg',
    },
});

defineEmits(['confirm', 'cancel']);
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="show"
                class="fixed inset-0 z-[70] flex items-end justify-center bg-black/80 backdrop-blur-sm px-4 pb-6"
            >
                <div class="w-full max-w-sm rounded-3xl border border-gray-700 bg-gray-900 p-6">
                    <div class="text-center mb-5">
                        <p class="text-4xl">🏆</p>
                        <h3 class="mt-2 text-xl font-bold text-white">Finalizar Treino?</h3>
                        <p class="mt-1 text-sm text-gray-400">Confira seu resumo antes de confirmar</p>
                    </div>

                    <div class="grid grid-cols-3 gap-3 mb-6">
                        <div class="rounded-2xl bg-gray-800 p-3 text-center">
                            <p class="text-xs text-gray-500">Duração</p>
                            <p class="mt-1 font-mono text-base font-bold text-violet-400">{{ elapsedDisplay }}</p>
                        </div>
                        <div class="rounded-2xl bg-gray-800 p-3 text-center">
                            <p class="text-xs text-gray-500">Séries</p>
                            <p class="mt-1 text-base font-bold text-emerald-400">{{ totalDoneSets }}</p>
                        </div>
                        <div class="rounded-2xl bg-gray-800 p-3 text-center">
                            <p class="text-xs text-gray-500">Volume</p>
                            <p class="mt-1 text-base font-bold text-orange-400">{{ volumeDisplay }}</p>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <button
                            @click="$emit('confirm')"
                            :disabled="finishing"
                            class="flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-orange-500 to-red-500 py-4 text-base font-bold text-white shadow-lg shadow-orange-500/30 transition hover:from-orange-600 hover:to-red-600 disabled:opacity-60"
                        >
                            <svg v-if="finishing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            {{ finishing ? 'Finalizando...' : '🔥 Confirmar e Finalizar' }}
                        </button>
                        <button
                            @click="$emit('cancel')"
                            class="w-full rounded-2xl border border-gray-700 py-3 text-sm font-semibold text-gray-400 transition hover:border-gray-600 hover:text-white"
                        >
                            Voltar ao Treino
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
