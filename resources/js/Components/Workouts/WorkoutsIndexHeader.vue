<script setup>
import BaseButton from '@/Components/UI/BaseButton.vue';
import { router } from '@inertiajs/vue3';

defineProps({
    todayDayOfWeekLabel: {
        type: String,
        default: '',
    },
});

defineEmits(['scan']);

const goToCreate = () => router.visit(route('workouts.create'));
</script>

<template>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-text-primary">
                Treinos
            </h2>
            <p v-if="todayDayOfWeekLabel" class="mt-0.5 text-sm text-text-secondary">
                Hoje é {{ todayDayOfWeekLabel }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <BaseButton variant="secondary" @click="$emit('scan')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.174C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" />
                </svg>
                Escanear Ficha
            </BaseButton>

            <BaseButton variant="primary" @click="goToCreate">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                </svg>
                Novo Treino
            </BaseButton>
        </div>
    </div>
</template>
