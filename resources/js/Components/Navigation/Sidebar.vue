<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
    open: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['close', 'logout']);

const page = usePage();

const navigation = [
    {
        name: 'Dashboard',
        route: 'dashboard',
        pattern: 'dashboard',
        icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    },
    {
        name: 'Treinos',
        route: 'workouts.index',
        pattern: 'workouts.*',
        icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
    },
    {
        name: 'Perfil',
        route: 'profile.edit',
        pattern: 'profile.*',
        icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    },
];
</script>

<template>
    <!-- Mobile backdrop -->
    <Transition
        enter-active-class="transition-opacity ease-linear duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity ease-linear duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="open"
            class="fixed inset-0 z-40 bg-gray-900/60 lg:hidden"
            @click="$emit('close')"
        />
    </Transition>

    <!-- Sidebar / drawer -->
    <aside
        class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full transform flex-col border-r border-gray-200 bg-white transition-transform duration-300 ease-in-out dark:border-gray-700 dark:bg-gray-800 lg:translate-x-0"
        :class="{ 'translate-x-0': open }"
    >
        <div class="flex h-16 shrink-0 items-center gap-2 border-b border-gray-200 px-6 dark:border-gray-700">
            <Link :href="route('dashboard')" class="flex items-center gap-2">
                <ApplicationLogo class="h-8 w-auto fill-current text-violet-500" />
                <span class="text-lg font-bold text-gray-800 dark:text-gray-100">IA-LIFT</span>
            </Link>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
            <Link
                v-for="item in navigation"
                :key="item.name"
                :href="route(item.route)"
                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition duration-150 ease-in-out"
                :class="
                    route().current(item.pattern)
                        ? 'bg-violet-500/10 text-violet-600 dark:bg-violet-500/15 dark:text-violet-400'
                        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700/50 dark:hover:text-gray-100'
                "
                @click="$emit('close')"
            >
                <svg
                    class="h-5 w-5 shrink-0"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        :d="item.icon"
                    />
                </svg>
                {{ item.name }}
            </Link>
        </nav>

        <div class="border-t border-gray-200 p-3 dark:border-gray-700">
            <div class="flex items-center gap-3 rounded-lg px-3 py-2.5">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-violet-500/15 text-sm font-semibold text-violet-600 dark:text-violet-400">
                    {{ page.props.auth.user.name.charAt(0).toUpperCase() }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-gray-800 dark:text-gray-100">
                        {{ page.props.auth.user.name }}
                    </p>
                    <button
                        type="button"
                        class="text-xs text-gray-500 hover:text-violet-600 dark:text-gray-400 dark:hover:text-violet-400"
                        @click="$emit('logout')"
                    >
                        Sair
                    </button>
                </div>
            </div>
        </div>
    </aside>
</template>
