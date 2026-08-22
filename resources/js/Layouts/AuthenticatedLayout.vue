<script setup>
import { ref, watch } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
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
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        <Sidebar :open="sidebarOpen" @close="sidebarOpen = false" @logout="logout" />

        <div class="flex min-h-screen flex-col lg:pl-64">
            <!-- Mobile top bar -->
            <div
                class="flex h-16 shrink-0 items-center gap-4 border-b border-gray-200 bg-white px-4 dark:border-gray-700 dark:bg-gray-800 lg:hidden"
            >
                <button
                    type="button"
                    class="-ms-1 inline-flex items-center justify-center rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus:outline-none dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
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

                <ApplicationLogo class="h-7 w-auto fill-current text-violet-500" />
                <span class="text-base font-bold text-gray-800 dark:text-gray-100">IA-LIFT</span>
            </div>

            <!-- Page Heading -->
            <header
                class="bg-white shadow dark:bg-gray-800"
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
                            'flex w-full max-w-sm items-center gap-3 rounded-2xl border bg-white/95 px-4 py-3.5 shadow-xl backdrop-blur dark:bg-gray-800/95',
                            toastVariant === 'error'
                                ? 'border-red-500/30 shadow-red-500/10'
                                : 'border-violet-500/30 shadow-violet-500/10',
                        ]"
                    >
                        <span
                            :class="[
                                'flex h-8 w-8 shrink-0 items-center justify-center rounded-full',
                                toastVariant === 'error'
                                    ? 'bg-red-500/15 text-red-600 dark:text-red-400'
                                    : 'bg-violet-500/15 text-violet-600 dark:text-violet-400',
                            ]"
                        >
                            <svg v-if="toastVariant === 'error'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                        </span>
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                            {{ toastMessage }}
                        </p>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
