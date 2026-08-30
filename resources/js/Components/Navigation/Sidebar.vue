<script setup>
import SetwaveLogo from '@/Components/Brand/SetwaveLogo.vue';
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
        name: 'Programas',
        route: 'programs.index',
        pattern: 'programs.*',
        icon: 'M4 6h16M4 10h16M4 14h10M4 18h10',
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
            class="fixed inset-0 z-40 bg-surface-base/70 lg:hidden"
            @click="$emit('close')"
        />
    </Transition>

    <!-- Sidebar / drawer -->
    <aside
        class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full transform flex-col border-r border-border-subtle bg-surface-raised transition-transform duration-300 ease-in-out lg:translate-x-0"
        :class="{ 'translate-x-0': open }"
    >
        <div class="flex h-16 shrink-0 items-center gap-2 border-b border-border-subtle px-6">
            <Link :href="route('dashboard')" class="flex items-center gap-2">
                <SetwaveLogo :size="28" variant="full" />
            </Link>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
            <Link
                v-for="item in navigation"
                :key="item.name"
                :href="route(item.route)"
                class="flex min-h-[48px] items-center gap-3 rounded-radius-md px-3 py-2.5 text-sm font-medium transition duration-150 ease-in-out"
                :class="
                    route().current(item.pattern)
                        ? 'border border-border-accent bg-accent-muted text-accent-text-strong shadow-md shadow-accent-glow-soft'
                        : 'border border-transparent text-text-secondary hover:bg-surface-overlay hover:text-text-primary'
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

        <div class="border-t border-border-subtle p-3">
            <div class="flex items-center gap-3 rounded-radius-md px-3 py-2.5">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-radius-full bg-accent-muted text-sm font-semibold text-accent-text-soft">
                    {{ page.props.auth.user.name.charAt(0).toUpperCase() }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-text-primary">
                        {{ page.props.auth.user.name }}
                    </p>
                    <button
                        type="button"
                        class="text-xs text-text-secondary hover:text-accent-text-soft"
                        @click="$emit('logout')"
                    >
                        Sair
                    </button>
                </div>
            </div>
        </div>
    </aside>
</template>
