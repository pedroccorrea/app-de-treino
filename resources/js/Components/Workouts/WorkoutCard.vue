<script setup>
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseCard from '@/Components/UI/BaseCard.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    workout: {
        type: Object,
        required: true,
    },
    currentPath: {
        type: String,
        default: '',
    },
});

defineEmits(['open', 'toggle-archive', 'delete']);
</script>

<template>
    <BaseCard
        role="button"
        tabindex="0"
        @click="$emit('open', workout)"
        @keydown.enter="$emit('open', workout)"
        :class="[
            'group cursor-pointer transition hover:-translate-y-0.5 hover:border-border-accent',
            workout.is_today ? 'ring-1 ring-border-accent' : '',
        ]"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex items-center gap-1.5">
                    <h3 class="truncate text-lg font-semibold text-text-primary transition group-hover:text-accent-text-soft">
                        {{ workout.name }}
                    </h3>
                    <Link
                        :href="route('workouts.edit', { workout: workout.id, return_to: currentPath })"
                        @click.stop
                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-radius-sm text-text-secondary transition hover:bg-accent-muted hover:text-accent-text-soft"
                        title="Editar treino"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </Link>
                </div>
                <p v-if="workout.description" class="mt-1 line-clamp-2 text-sm text-text-body">
                    {{ workout.description }}
                </p>
            </div>

            <BaseBadge tone="accent" class="shrink-0">
                {{ workout.exercises_count }}
                {{ workout.exercises_count === 1 ? 'exercício' : 'exercícios' }}
            </BaseBadge>
        </div>

        <div
            v-if="workout.is_today || workout.days_of_week_labels.length"
            class="mt-3 flex flex-wrap items-center gap-1.5"
        >
            <BaseBadge v-if="workout.is_today" tone="warning">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" class="mr-1" aria-hidden="true">
                    <path d="M12.963 2.286a.75.75 0 00-1.071-.136 9.742 9.742 0 00-3.539 6.176 7.547 7.547 0 01-1.705-1.715.75.75 0 00-1.152-.082A9 9 0 1015.68 4.534a7.46 7.46 0 01-2.717-2.248z" />
                </svg>
                Treino de Hoje
            </BaseBadge>
            <BaseBadge v-if="workout.days_of_week_labels.length" tone="neutral">
                {{ workout.days_of_week_labels.join(', ') }}
            </BaseBadge>
        </div>

        <div
            v-if="workout.muscle_groups.length"
            class="mt-4 flex flex-wrap gap-1.5"
        >
            <BaseBadge v-for="muscle in workout.muscle_groups.slice(0, 5)" :key="`${workout.id}-${muscle}`" tone="neutral">
                {{ muscle }}
            </BaseBadge>
            <BaseBadge v-if="workout.muscle_groups.length > 5" tone="neutral">
                +{{ workout.muscle_groups.length - 5 }}
            </BaseBadge>
        </div>

        <ul
            v-if="workout.exercises.length"
            class="mt-4 space-y-2 border-t border-border-subtle pt-4"
        >
            <li
                v-for="exercise in workout.exercises.slice(0, 3)"
                :key="exercise.id"
                class="flex items-center justify-between text-sm"
            >
                <span class="font-medium text-text-primary">
                    {{ exercise.name }}
                </span>
                <span
                    v-if="exercise.target_sets || exercise.target_reps"
                    class="text-xs font-semibold text-text-secondary"
                >
                    <template v-if="exercise.target_sets">
                        {{ exercise.target_sets }}x
                    </template>
                    {{ exercise.target_reps }}
                </span>
            </li>
            <li
                v-if="workout.exercises.length > 3"
                class="text-xs text-text-secondary"
            >
                +{{ workout.exercises.length - 3 }} exercícios
            </li>
        </ul>

        <div class="mt-4 flex items-center gap-2 border-t border-border-subtle pt-3">
            <BaseButton variant="ghost" @click.stop="$emit('toggle-archive', workout)">
                {{ workout.is_active ? 'Arquivar' : 'Reativar' }}
            </BaseButton>
            <BaseButton variant="danger" @click.stop="$emit('delete', workout)">
                Excluir
            </BaseButton>
        </div>
    </BaseCard>
</template>
