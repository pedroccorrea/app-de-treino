<script setup>
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseCard from '@/Components/UI/BaseCard.vue';

// Detalhe de um treino cacheado no IndexedDB, exibido no lugar da navegação
// Inertia normal (`router.visit`) quando o dispositivo está offline — abrir
// a página real de detalhe exigiria uma requisição de rede, que falharia
// sem conexão. Componente "burro": só recebe o treino já lido do cache via
// props; quem lê o IndexedDB e decide quando mostrar isso é a Página
// (`Workouts/Index.vue`).
defineProps({
    workout: { type: Object, required: true },
});

defineEmits(['start', 'close']);

const formatTarget = (exercise) => {
    const parts = [];
    if (exercise.target_sets) parts.push(`${exercise.target_sets} séries`);
    if (exercise.target_reps) parts.push(`${exercise.target_reps} reps`);
    return parts.length ? parts.join(' × ') : 'Metas não definidas';
};
</script>

<template>
    <div class="fixed inset-0 z-50 flex flex-col bg-surface-base text-text-primary">
        <header class="flex items-center justify-between border-b border-border-subtle px-4 py-3">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-semibold">{{ workout.name }}</h2>
                    <BaseBadge tone="warning">MODO OFFLINE</BaseBadge>
                </div>
                <p class="mt-0.5 text-xs text-text-secondary">Carregado do cache deste dispositivo</p>
            </div>
            <button
                type="button"
                class="rounded-radius-sm p-2 text-text-secondary transition hover:bg-surface-overlay hover:text-text-primary"
                @click="$emit('close')"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </header>

        <div class="flex-1 overflow-y-auto px-4 py-5">
            <p v-if="workout.description" class="mb-4 text-sm text-text-body">{{ workout.description }}</p>

            <div v-if="workout.muscle_groups?.length" class="mb-4 flex flex-wrap gap-2">
                <BaseBadge v-for="muscle in workout.muscle_groups" :key="muscle" tone="neutral">
                    {{ muscle }}
                </BaseBadge>
            </div>

            <BaseCard :padded="false">
                <ol class="divide-y divide-border-subtle">
                    <li
                        v-for="(exercise, index) in workout.exercises"
                        :key="exercise.id"
                        class="flex items-center gap-3 p-4"
                    >
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-radius-full bg-accent-muted text-xs font-bold text-accent-label">
                            {{ index + 1 }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-text-primary">{{ exercise.name }}</p>
                            <p class="mt-0.5 text-xs text-text-secondary">{{ exercise.primary_muscle }}</p>
                        </div>
                        <span class="shrink-0 text-xs font-semibold text-accent-text-soft">
                            {{ formatTarget(exercise) }}
                        </span>
                    </li>
                </ol>
            </BaseCard>
        </div>

        <footer class="border-t border-border-subtle px-4 py-3">
            <BaseButton variant="primary" class="w-full" @click="$emit('start')">
                Iniciar Treino (offline)
            </BaseButton>
            <p class="mt-2 text-center text-xs text-text-secondary">
                Suas séries serão salvas neste dispositivo e sincronizadas quando a internet voltar.
            </p>
        </footer>
    </div>
</template>
