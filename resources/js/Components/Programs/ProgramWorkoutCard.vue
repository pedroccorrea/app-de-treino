<script setup>
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseCard from '@/Components/UI/BaseCard.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    workout: {
        type: Object,
        required: true,
    },
    returnTo: {
        type: String,
        default: '',
    },
});

defineEmits(['open', 'delete']);
</script>

<template>
    <BaseCard
        role="button"
        tabindex="0"
        @click="$emit('open', workout)"
        @keydown.enter="$emit('open', workout)"
        class="group flex cursor-pointer items-center justify-between gap-3 transition hover:-translate-y-0.5 hover:border-border-accent"
    >
        <div class="min-w-0">
            <div class="flex items-center gap-1.5">
                <h3 class="truncate text-base font-semibold text-text-primary transition group-hover:text-accent-text-soft">
                    {{ workout.name }}
                </h3>
                <Link
                    :href="route('workouts.edit', { workout: workout.id, return_to: returnTo })"
                    @click.stop
                    class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-radius-sm text-text-secondary transition hover:bg-accent-muted hover:text-accent-text-soft"
                    title="Editar treino"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                </Link>
            </div>
            <p v-if="workout.description" class="mt-0.5 truncate text-sm text-text-secondary">
                {{ workout.description }}
            </p>
            <p class="mt-1 text-xs font-semibold text-text-secondary">
                {{ workout.exercises_count }}
                {{ workout.exercises_count === 1 ? 'exercício' : 'exercícios' }}
            </p>
        </div>

        <BaseButton variant="danger" class="shrink-0" @click.stop="$emit('delete', workout)" title="Desvincular do programa">
            Desvincular
        </BaseButton>
    </BaseCard>
</template>
