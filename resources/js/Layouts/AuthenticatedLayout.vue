<script setup>
import { ref, watch } from 'vue';
import SetwaveLogo from '@/Components/Brand/SetwaveLogo.vue';
import Sidebar from '@/Components/Navigation/Sidebar.vue';
import { router, usePage } from '@inertiajs/vue3';

const sidebarOpen = ref(false);

const logout = () => {
    router.post(route('logout'));
};

// ─── Success / error toasts ─────────────────────────────────────────────────
// Whenever a redirect carries a `flash.success` or `flash.error` message,
// show it as a self-dismissing toast on top of whatever page the redirect
// landed on.
const page = usePage();
const toastMessage = ref(null);
const toastVariant = ref('success');
let toastTimeout = null;

const showToast = (message, variant) => {
    if (!message) return;

    toastMessage.value = message;
    toastVariant.value = variant;
    clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => {
        toastMessage.value = null;
    }, 4000);
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
</script>

<template>
    <div class="min-h-screen bg-surface-base">
        <Sidebar :open="sidebarOpen" @close="sidebarOpen = false" @logout="logout" />

        <div class="flex min-h-screen flex-col lg:pl-64">
            <!-- Mobile top bar -->
            <div
                class="flex h-16 shrink-0 items-center gap-4 border-b border-border-subtle bg-surface-raised px-4 lg:hidden"
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
                <div
                    v-if="toastMessage"
                    class="fixed inset-x-4 top-4 z-[100] flex justify-center sm:inset-x-auto sm:right-6 sm:justify-end"
                >
                    <div
                        :class="[
                            'flex w-full max-w-sm items-center gap-3 rounded-radius-lg border bg-surface-overlay px-4 py-3.5 shadow-xl backdrop-blur',
                            toastVariant === 'error' ? 'border-danger/30' : 'border-border-accent',
                        ]"
                    >
                        <span
                            :class="[
                                'flex h-8 w-8 shrink-0 items-center justify-center rounded-radius-full',
                                toastVariant === 'error' ? 'bg-danger/15 text-danger' : 'bg-accent-muted text-accent-text-soft',
                            ]"
                        >
                            <svg v-if="toastVariant === 'error'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                        </span>
                        <p class="text-sm font-semibold text-text-primary">
                            {{ toastMessage }}
                        </p>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
