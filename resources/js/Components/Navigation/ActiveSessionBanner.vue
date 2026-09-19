<script setup>
import { getActiveSession } from '@/Utils/offlineStorage';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const page = usePage();

// Prop compartilhada pelo Inertia (`HandleInertiaRequests`): sempre a
// verdade do servidor quando a requisição realmente chegou nele.
const serverSession = computed(() => page.props.activeWorkoutSession ?? null);

// ─── Autoridade do IndexedDB (Phase 36 / corrigido na Phase 38) ────────────
// Offline, `page.props.activeWorkoutSession` é imprestável: ou veio de um
// JSON cacheado antes de o treino começar (banner some), ou de uma página
// sintetizada pelo Service Worker. O registro local (`active_session`,
// mantido por `WorkoutSessions/Active.vue`, pelo `AuthenticatedLayout` e
// pela fila de sincronização) é o estado físico deste aparelho — é ele que
// manda quando não há rede.
const isOnline = ref(typeof navigator === 'undefined' || navigator.onLine);
const localSession = ref(null);

async function syncLocalSession() {
    try {
        localSession.value = await getActiveSession();
    } catch (_) {
        localSession.value = null;
    }
}

// Online a prop do servidor manda, com o registro local como rede de
// segurança para o caso clássico de `navigator.onLine` mentir (o navegador
// se diz conectado, mas a resposta veio do cache do Service Worker).
const activeSession = computed(() =>
    isOnline.value ? (serverSession.value ?? localSession.value) : localSession.value,
);

// Sessão nascida offline: o `id` ainda é o UUID gerado no aparelho, não
// existe `WorkoutSession` no servidor, e a tela do treino é renderizada por
// `Workouts/Show.vue` (que a restaura do IndexedDB). O destino do banner,
// nesse caso, é a página da ficha — que funciona online e offline.
const isOfflineBornSession = (session) =>
    session?.offline_only === true || !Number.isFinite(Number(session?.id));

const sessionHref = computed(() => {
    const session = activeSession.value;
    if (!session) return null;

    if (isOfflineBornSession(session)) {
        return session.workout_id ? route('workouts.show', session.workout_id) : null;
    }

    return route('workout-sessions.show', session.id);
});

// Nunca mostra o atalho para a tela em que o usuário já está — inclusive
// quando a sessão offline está sendo renderizada dentro da própria página da
// ficha (`Workouts/Show.vue`).
const isOnSessionPage = computed(() => {
    if (!sessionHref.value) return false;

    const currentPath = page.url.split('?')[0];
    const targetPath = new URL(sessionHref.value, window.location.origin).pathname;

    return currentPath === targetPath;
});

const showBanner = computed(() => Boolean(sessionHref.value) && !isOnSessionPage.value);

// Recarrega o registro local a cada navegação e a cada mudança da prop do
// servidor: sem isso o banner só se atualizava quando o Inertia trocava a
// prop — exatamente o que nunca acontece numa navegação offline servida pelo
// cache, deixando o botão flutuante sumido até a rede voltar.
watch(serverSession, syncLocalSession, { immediate: true });

function updateOnlineStatus() {
    isOnline.value = navigator.onLine;
    syncLocalSession();
}

let stopNavigateListener = null;

onMounted(() => {
    syncLocalSession();
    stopNavigateListener = router.on('navigate', () => syncLocalSession());
    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);
});

// ─── Live elapsed time ────────────────────────────────────────────────────────
const now = ref(Date.now());
let tick = null;

const stopTick = () => {
    clearInterval(tick);
    tick = null;
};

// O intervalo só existe enquanto houver sessão ativa: ao finalizar o treino,
// `activeSession` vira null, o timer é destruído imediatamente e nenhum
// resíduo (id antigo, contagem congelada) sobra para a próxima sessão.
watch(
    () => activeSession.value?.id ?? null,
    (id) => {
        stopTick();
        now.value = Date.now();

        if (id !== null) {
            tick = setInterval(() => {
                now.value = Date.now();
            }, 1000);
        }
    },
    { immediate: true },
);

onUnmounted(() => {
    stopTick();
    stopNavigateListener?.();
    window.removeEventListener('online', updateOnlineStatus);
    window.removeEventListener('offline', updateOnlineStatus);
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
                :href="sessionHref"
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
