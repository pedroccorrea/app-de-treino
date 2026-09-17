<script setup>
import { onMounted, ref, watch } from 'vue';
import SetwaveLogo from '@/Components/Brand/SetwaveLogo.vue';
import ActiveSessionBanner from '@/Components/Navigation/ActiveSessionBanner.vue';
import Sidebar from '@/Components/Navigation/Sidebar.vue';
import Toast from '@/Components/Notifications/Toast.vue';
import { router, usePage } from '@inertiajs/vue3';
import { warmWorkoutsCache } from '@/Utils/cacheWarming';
import { clearActiveSession, setActiveSession } from '@/Utils/offlineStorage';

const sidebarOpen = ref(false);

const logout = () => {
    router.post(route('logout'));
};

// ─── Success / error toasts ─────────────────────────────────────────────────
// Whenever a redirect carries a `flash.success` or `flash.error` message,
// show it as a self-dismissing toast on top of whatever page the redirect
// landed on. The Toast component owns its own auto-dismiss timer and swipe
// gesture; this layout only tracks which message/variant is currently shown.
const page = usePage();
const toastMessage = ref(null);
const toastVariant = ref('success');

const showToast = (message, variant) => {
    if (!message) return;

    toastMessage.value = message;
    toastVariant.value = variant;
};

watch(
    () => page.props.flash?.success,
    (message) => showToast(message, 'success'),
    { immediate: true },
);

watch(
    () => page.props.flash?.error,
    (message) => showToast(message, 'error'),
    { immediate: true },
);

// ─── Autoridade do IndexedDB (Phase 36) ─────────────────────────────────────
// Toda página autenticada compartilha `activeWorkoutSession` (ver
// `HandleInertiaRequests`), que é sempre fresco quando a requisição chegou a
// bater no servidor. Reconcilia essa verdade com a cópia local (IndexedDB)
// que `ActiveSessionBanner.vue` e `Dashboard.vue` consultam em modo offline —
// mantendo-a sempre alinhada ao estado real do servidor sempre que há
// conexão, inclusive quando a sessão foi iniciada/finalizada em outro
// dispositivo ou aba.
watch(
    () => page.props.activeWorkoutSession,
    (session) => {
        if (typeof navigator === 'undefined' || !navigator.onLine) return;

        if (session) {
            setActiveSession(session);
        } else {
            clearActiveSession();
        }
    },
    { immediate: true },
);

// ─── Cache Warming ──────────────────────────────────────────────────────────
// Assim que o layout monta (ou seja, em qualquer página autenticada), aquece
// silenciosamente o cache offline com o JSON do Inertia de todas as fichas
// do usuário — assim abrir qualquer treino depois, mesmo sem conexão, tende
// a funcionar mesmo que aquele dispositivo nunca tenha visitado aquela
// página antes.
onMounted(() => {
    if (navigator.onLine) {
        warmWorkoutsCache(page.props.userWorkoutIds, page.version);
    }
});
</script>

<template>
    <div class="min-h-screen bg-surface-base">
        <Sidebar :open="sidebarOpen" @close="sidebarOpen = false" @logout="logout" />

        <ActiveSessionBanner />

        <div class="flex min-h-screen flex-col lg:pl-64">
            <!-- Mobile top bar -->
            <div
                class="sticky top-0 z-40 flex h-16 shrink-0 items-center gap-4 border-b border-border-subtle bg-surface-base/90 px-4 backdrop-blur-md lg:hidden"
            >
                <button
                    type="button"
                    class="-ms-1 inline-flex h-12 w-12 items-center justify-center rounded-radius-md text-text-secondary hover:bg-surface-overlay hover:text-text-primary focus:outline-none"
                    @click="sidebarOpen = true"
                >
                    <svg
                        class="h-6 w-6"
                        stroke="currentColor"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16"
                        />
                    </svg>
                </button>

                <SetwaveLogo :size="24" variant="full" />
            </div>

            <!-- Page Heading -->
            <header
                class="border-b border-border-subtle bg-surface-raised"
                v-if="$slots.header"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1">
                <slot />
            </main>
        </div>

        <!-- Success / Error Toast -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0 -translate-y-2 sm:translate-y-0 sm:translate-x-4"
                enter-to-class="opacity-100 translate-y-0 translate-x-0"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0 translate-x-0"
                leave-to-class="opacity-0 -translate-y-2 sm:translate-y-0 sm:translate-x-4"
            >
                <Toast
                    :message="toastMessage"
                    :variant="toastVariant"
                    @close="toastMessage = null"
                />
            </Transition>
        </Teleport>
    </div>
</template>
