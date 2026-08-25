<script setup>
defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['continue', 'confirm']);
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="show"
                class="fixed inset-0 z-[70] flex items-center justify-center bg-black/80 backdrop-blur-sm px-4"
            >
                <div class="w-full max-w-sm rounded-3xl border border-gray-700 bg-gray-900 p-6">
                    <h3 class="text-lg font-bold text-white">Série em Execução</h3>
                    <p class="mt-2 text-sm text-gray-400">
                        Você já iniciou este exercício. Deseja finalizar a série ou trocar de exercício e zerar o cronômetro?
                    </p>
                    <div class="mt-5 flex flex-col gap-2">
                        <button
                            @click="$emit('continue')"
                            class="w-full rounded-2xl bg-violet-600 py-3 text-sm font-bold text-white transition hover:bg-violet-700"
                        >
                            Continuar Série
                        </button>
                        <button
                            @click="$emit('confirm')"
                            class="w-full rounded-2xl bg-gray-700 py-3 text-sm font-bold text-white transition hover:bg-gray-600"
                        >
                            Trocar e Zerar Timer
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
