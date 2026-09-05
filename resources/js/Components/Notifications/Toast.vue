<script setup>
import { ref, watch } from 'vue';

// Flash message autossuficiente: recebe a mensagem/variante do pai (que
// escuta `page.props.flash`) e cuida sozinho do próprio ciclo de vida —
// timer de auto-fechamento e gesto de arrastar para descartar.
const props = defineProps({
    message: {
        type: String,
        default: null,
    },
    variant: {
        type: String,
        default: 'success',
    },
});

const emit = defineEmits(['close']);

const AUTO_DISMISS_MS = 3500;
const SWIPE_DISMISS_THRESHOLD_PX = 80;

const dragOffsetX = ref(0);
const dragging = ref(false);
let touchStartX = 0;
let autoDismissTimeout = null;

const clearAutoDismiss = () => clearTimeout(autoDismissTimeout);

const scheduleAutoDismiss = () => {
    clearAutoDismiss();
    autoDismissTimeout = setTimeout(() => emit('close'), AUTO_DISMISS_MS);
};

watch(
    () => props.message,
    (message) => {
        dragOffsetX.value = 0;
        if (message) {
            scheduleAutoDismiss();
        } else {
            clearAutoDismiss();
        }
    },
    { immediate: true },
);

const onTouchStart = (event) => {
    touchStartX = event.touches[0].clientX;
    dragging.value = true;
    clearAutoDismiss();
};

const onTouchMove = (event) => {
    if (!dragging.value) return;
    dragOffsetX.value = event.touches[0].clientX - touchStartX;
};

const onTouchEnd = () => {
    dragging.value = false;

    if (Math.abs(dragOffsetX.value) > SWIPE_DISMISS_THRESHOLD_PX) {
        emit('close');
        return;
    }

    dragOffsetX.value = 0;
    scheduleAutoDismiss();
};

const dragStyle = () => ({
    transform: `translateX(${dragOffsetX.value}px)`,
    opacity: String(1 - Math.min(Math.abs(dragOffsetX.value) / 150, 0.85)),
    transition: dragging.value ? 'none' : 'transform 0.2s ease, opacity 0.2s ease',
});
</script>

<template>
    <div
        v-if="message"
        class="fixed inset-x-4 top-4 z-[100] flex justify-center sm:inset-x-auto sm:right-6 sm:justify-end"
        @touchstart="onTouchStart"
        @touchmove="onTouchMove"
        @touchend="onTouchEnd"
    >
        <div
            :style="dragStyle()"
            :class="[
                'flex w-full max-w-sm items-center gap-3 rounded-radius-lg border bg-surface-overlay px-4 py-3.5 shadow-xl backdrop-blur touch-pan-y',
                variant === 'error' ? 'border-danger/30' : 'border-border-accent',
            ]"
        >
            <span
                :class="[
                    'flex h-8 w-8 shrink-0 items-center justify-center rounded-radius-full',
                    variant === 'error' ? 'bg-danger/15 text-danger' : 'bg-accent-muted text-accent-text-soft',
                ]"
            >
                <svg v-if="variant === 'error'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
            </span>
            <p class="flex-1 text-sm font-semibold text-text-primary">
                {{ message }}
            </p>
            <button
                type="button"
                aria-label="Fechar notificação"
                class="shrink-0 rounded-radius-full p-1 text-text-tertiary transition hover:bg-surface-base hover:text-text-primary"
                @click="emit('close')"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>
</template>
