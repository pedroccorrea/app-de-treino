<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    todayDayOfWeekLabel: {
        type: String,
        default: '',
    },
});

defineEmits(['scan']);
</script>

<template>
    <div
        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <div>
            <h2
                class="text-xl font-bold leading-tight text-gray-900 dark:text-gray-100"
            >
                Treinos
            </h2>
            <p
                v-if="todayDayOfWeekLabel"
                class="mt-0.5 text-sm text-gray-500 dark:text-gray-400"
            >
                Hoje é {{ todayDayOfWeekLabel }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <button
                type="button"
                @click="$emit('scan')"
                class="inline-flex items-center justify-center gap-2 rounded-xl border border-violet-600 px-4 py-2.5 text-sm font-semibold text-violet-600 transition hover:bg-violet-50 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 dark:text-violet-400 dark:hover:bg-violet-500/10 dark:focus:ring-offset-gray-800"
            >
                📷 Escanear Ficha
            </button>

            <Link
                :href="route('workouts.create')"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-violet-500/20 transition hover:bg-violet-700 hover:shadow-violet-500/30 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
            >
                <svg
                    class="h-5 w-5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2.5"
                        d="M12 4v16m8-8H4"
                    />
                </svg>
                Novo Treino
            </Link>
        </div>
    </div>
</template>
