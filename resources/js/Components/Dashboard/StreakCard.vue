<script setup>
import BaseCard from '@/Components/UI/BaseCard.vue';
import SectionLabel from '@/Components/UI/SectionLabel.vue';
import SetwaveLogo from '@/Components/Brand/SetwaveLogo.vue';

defineProps({
    streak: {
        type: Number,
        default: 0,
    },
});
</script>

<template>
    <BaseCard class="flex items-center justify-between gap-3">
        <div v-if="streak > 0">
            <SectionLabel tone="accent">Sequência de Treinos</SectionLabel>
            <p class="mt-1 text-2xl font-semibold text-text-primary [font-variant-numeric:tabular-nums]">
                {{ streak }} {{ streak === 1 ? 'dia seguido' : 'dias seguidos' }}
            </p>
        </div>
        <div v-else>
            <SectionLabel tone="secondary">Sequência de Treinos</SectionLabel>
            <p class="mt-1 text-sm text-text-body">
                Você ainda não tem histórico de treinos. Complete um treino para começar sua sequência.
            </p>
        </div>

        <span
            v-if="streak > 0"
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-radius-full bg-accent-muted text-accent-label"
        >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M14.615 1.595a.75.75 0 01.359.852L12.982 9.75h7.268a.75.75 0 01.548 1.262l-10.5 11.25a.75.75 0 01-1.272-.71l1.992-7.302H3.75a.75.75 0 01-.548-1.262l10.5-11.25a.75.75 0 01.913-.143z" />
            </svg>
        </span>
        <SetwaveLogo v-else :size="32" variant="mark" />
    </BaseCard>
</template>
