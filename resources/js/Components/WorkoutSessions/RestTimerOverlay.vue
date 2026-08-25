<script setup>
defineProps({
    active: {
        type: Boolean,
        default: false,
    },
    display: {
        type: String,
        default: '00:00',
    },
    percent: {
        type: Number,
        default: 0,
    },
    nextSetNumber: {
        type: Number,
        default: null,
    },
});

defineEmits(['end-rest', 'add-time']);
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="active"
                class="fixed inset-0 z-[60] flex flex-col items-center justify-center bg-black/85 backdrop-blur-sm px-6"
                style="overflow: hidden;"
            >
                <div class="w-full max-w-sm">
                    <p class="mb-2 text-center text-xs font-semibold uppercase tracking-widest text-violet-400">
                        ⏸ Descansando
                    </p>

                    <div class="text-center">
                        <p class="font-mono text-8xl font-bold text-white leading-none tabular-nums">
                            {{ display }}
                        </p>
                    </div>

                    <div class="mt-6 h-2 overflow-hidden rounded-full bg-gray-800">
                        <div
                            class="h-full rounded-full bg-gradient-to-r from-violet-500 to-violet-300 transition-all duration-1000"
                            :style="{ width: percent + '%' }"
                        />
                    </div>

                    <div class="mt-6 flex flex-col gap-3">
                        <button
                            @click="$emit('end-rest')"
                            class="w-full rounded-2xl bg-gradient-to-r from-violet-600 to-violet-500 py-4 text-base font-bold text-white shadow-lg shadow-violet-500/30 transition hover:from-violet-700 hover:to-violet-600 active:scale-[0.98]"
                        >
                            Encerrar Descanso
                        </button>
                        <button
                            @click="$emit('add-time', 30)"
                            class="w-full rounded-2xl border border-gray-700 bg-gray-900/80 py-3 text-sm font-semibold text-gray-300 transition hover:border-gray-600 hover:text-white active:scale-[0.98]"
                        >
                            + 30 segundos
                        </button>
                    </div>

                    <p
                        v-if="nextSetNumber"
                        class="mt-4 text-center text-xs text-gray-500"
                    >
                        Próxima: Série {{ nextSetNumber }}
                    </p>
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
