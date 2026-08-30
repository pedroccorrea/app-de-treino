<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const page = usePage();

const activeSession = computed(() => page.props.activeWorkoutSession ?? null);

const isOnSessionPage = computed(() => route().current('workout-sessions.show'));

const showBanner = computed(() => activeSession.value !== null && !isOnSessionPage.value);

// ─── Live elapsed time ────────────────────────────────────────────────────────
const now = ref(Date.now());
let tick = null;

onMounted(() => {
    tick = setInterval(() => {
        now.value = Date.now();
    }, 1000);
});

onUnmounted(() => {
    clearInterval(tick);
});

const elapsedDisplay = computed(() => {
    if (!activeSession.value?.started_at) return '00:00';

    const seconds = Math.max(
        0,
        Math.floor((now.value - new Date(activeSession.value.started_at).getTime()) / 1000),
    );
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;

    return hours > 0
        ? `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
        : `${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <Link
                v-if="showBanner"
                :href="route('workout-sessions.show', activeSession.id)"
                class="fixed right-4 top-20 z-50 flex items-center gap-2 rounded-radius-full border border-border-accent bg-accent-muted px-4 py-2 text-sm font-semibold text-accent-text-strong shadow-lg shadow-accent-glow backdrop-blur transition hover:bg-accent-glow-soft-hover lg:top-4"
            >
                <span class="relative flex h-2.5 w-2.5 shrink-0">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-radius-full bg-accent opacity-75" />
                    <span class="relative inline-flex h-2.5 w-2.5 rounded-radius-full bg-accent" />
                </span>
                <span class="whitespace-nowrap">🔥 Treino em andamento</span>
                <span class="whitespace-nowrap font-mono text-xs text-accent-text-soft">{{ elapsedDisplay }}</span>
            </Link>
        </Transition>
    </Teleport>
</template>
