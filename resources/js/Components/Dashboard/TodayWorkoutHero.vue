<script setup>
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseCard from '@/Components/UI/BaseCard.vue';
import SectionLabel from '@/Components/UI/SectionLabel.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    workout: {
        type: Object,
        required: true,
    },
});

const starting = ref(false);

const startWorkout = () => {
    starting.value = true;
    router.post(
        route('workouts.start', props.workout.id),
        {},
        { onFinish: () => { starting.value = false; } },
    );
};
</script>

<template>
    <BaseCard>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="var(--accent-label)" aria-hidden="true">
                    <path d="M12.963 2.286a.75.75 0 00-1.071-.136 9.742 9.742 0 00-3.539 6.176 7.547 7.547 0 01-1.705-1.715.75.75 0 00-1.152-.082A9 9 0 1015.68 4.534a7.46 7.46 0 01-2.717-2.248z" />
                </svg>
                <SectionLabel tone="accent">Treino de Hoje</SectionLabel>
            </div>
            <BaseBadge v-if="workout.program_name" tone="neutral">
                {{ workout.program_name }}
            </BaseBadge>
        </div>

        <h3 class="mt-3 text-2xl font-semibold leading-tight text-text-primary">
            {{ workout.name }}
        </h3>

        <div v-if="workout.muscle_groups.length" class="mt-3 flex flex-wrap gap-1.5">
            <BaseBadge v-for="muscle in workout.muscle_groups" :key="muscle" tone="neutral">
                {{ muscle }}
            </BaseBadge>
        </div>

        <p class="mt-2 text-sm text-text-secondary">
            {{ workout.exercises_count }}
            {{ workout.exercises_count === 1 ? 'exercício' : 'exercícios' }}
        </p>

        <BaseButton
            variant="primary"
            ripple
            :disabled="starting"
            class="mt-5 w-full"
            @click="startWorkout"
        >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M12.963 2.286a.75.75 0 00-1.071-.136 9.742 9.742 0 00-3.539 6.176 7.547 7.547 0 01-1.705-1.715.75.75 0 00-1.152-.082A9 9 0 1015.68 4.534a7.46 7.46 0 01-2.717-2.248z" />
            </svg>
            {{ starting ? 'Iniciando...' : 'Iniciar Treino Agora' }}
        </BaseButton>
    </BaseCard>
</template>
