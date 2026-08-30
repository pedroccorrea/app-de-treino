<script setup>
import BaseCard from '@/Components/UI/BaseCard.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    activeWorkouts: {
        type: Array,
        default: () => [],
    },
});

const startingId = ref(null);

const startWorkout = (workout) => {
    startingId.value = workout.id;
    router.post(
        route('workouts.start', workout.id),
        {},
        { onFinish: () => { startingId.value = null; } },
    );
};
</script>

<template>
    <BaseCard class="border-dashed text-center">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-radius-full bg-accent-muted text-accent-label">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21.752 15.002A9.72 9.72 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
            </svg>
        </div>

        <h3 class="mt-3 text-lg font-semibold text-text-primary">
            Hoje é dia de descanso!
        </h3>
        <p class="mt-1 text-sm text-text-secondary">
            Ou escolha um treino abaixo
        </p>

        <div v-if="activeWorkouts.length" class="mt-4 flex flex-wrap justify-center gap-2">
            <button
                v-for="workout in activeWorkouts"
                :key="workout.id"
                type="button"
                :disabled="startingId === workout.id"
                @click="startWorkout(workout)"
                class="inline-flex min-h-[48px] items-center rounded-radius-md border border-border-subtle bg-transparent px-4 text-sm font-semibold text-text-primary transition hover:border-border-accent hover:text-accent-text-strong disabled:opacity-60"
            >
                {{ workout.name }}
            </button>
        </div>
    </BaseCard>
</template>
