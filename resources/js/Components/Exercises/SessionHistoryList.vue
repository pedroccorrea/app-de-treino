<script setup>
import BaseCard from '@/Components/UI/BaseCard.vue';

defineProps({
    sessions: {
        // [{ id, date, sets: [{ set_number, weight, reps }] }], mais recente primeiro
        type: Array,
        required: true,
    },
});

const formatDate = (isoDate) =>
    new Date(`${isoDate}T00:00:00`).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });

const formatSet = (set) => `${set.weight !== null ? `${set.weight}kg` : 'Peso corporal'} × ${set.reps}`;
</script>

<template>
    <div v-if="sessions.length === 0" class="rounded-radius-md border border-dashed border-border-subtle px-4 py-10 text-center">
        <p class="text-sm text-text-secondary">
            Nenhuma sessão concluída com este exercício neste período.
        </p>
    </div>

    <ol v-else class="space-y-2">
        <li v-for="session in sessions" :key="session.id">
            <BaseCard>
                <p class="text-xs uppercase tracking-[0.12em] text-text-secondary">
                    {{ formatDate(session.date) }}
                </p>
                <ul class="mt-2 flex flex-wrap gap-2">
                    <li
                        v-for="set in session.sets"
                        :key="set.set_number"
                        class="rounded-radius-sm border border-border-subtle bg-surface-overlay/40 px-2.5 py-1 text-[13px] font-medium text-text-primary [font-variant-numeric:tabular-nums]"
                    >
                        {{ formatSet(set) }}
                    </li>
                </ul>
            </BaseCard>
        </li>
    </ol>
</template>
