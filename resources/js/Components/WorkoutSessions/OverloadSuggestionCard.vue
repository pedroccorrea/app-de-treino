<script setup>
import { computed } from 'vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import SectionLabel from '@/Components/UI/SectionLabel.vue';

const props = defineProps({
    suggestedLoad: {
        type: Number,
        default: null,
    },
    suggestedReps: {
        type: Number,
        default: null,
    },
    previousLoad: {
        type: Number,
        default: null,
    },
    previousReps: {
        type: Number,
        default: null,
    },
    rationale: {
        type: String,
        default: null,
    },
});

defineEmits(['apply', 'dismiss']);

const fmt = (n) => Number(n).toLocaleString('pt-BR', { maximumFractionDigits: 2 });

const loadDisplay = computed(() => (props.suggestedLoad !== null ? fmt(props.suggestedLoad) : '—'));
const previousDisplay = computed(() => {
    if (props.previousLoad === null) return null;
    return props.previousReps !== null
        ? `${fmt(props.previousLoad)} × ${props.previousReps}`
        : fmt(props.previousLoad);
});
</script>

<template>
    <div
        class="relative flex flex-col gap-4 overflow-hidden rounded-radius-lg border border-accent/[.26] bg-gradient-suggestion p-5 pb-[18px] shadow-[0_24px_60px_rgba(0,0,0,0.5),inset_0_1px_0_rgba(255,255,255,0.04)]"
    >
        <svg
            width="390"
            height="90"
            viewBox="0 0 390 90"
            fill="none"
            class="pointer-events-none absolute -right-10 -top-3.5 opacity-50"
        >
            <path
                d="M0 60C30 60 40 22 70 22s40 30 70 30 40-42 70-42 40 34 70 34 40-30 70-30"
                stroke="var(--accent)"
                stroke-opacity="0.35"
                stroke-width="1.2"
            />
            <path
                d="M0 76C30 76 40 38 70 38s40 30 70 30 40-42 70-42 40 34 70 34 40-30 70-30"
                stroke="var(--accent)"
                stroke-opacity="0.16"
                stroke-width="1.2"
            />
        </svg>

        <div class="relative flex items-center gap-2.5">
            <svg width="22" height="10" viewBox="0 0 22 10" fill="none">
                <path
                    d="M1 5C3 1.4 5 1.4 7 5s4 3.6 6 0 4-3.6 6 0"
                    stroke="var(--accent-label)"
                    stroke-width="1.5"
                    stroke-linecap="round"
                />
            </svg>
            <SectionLabel tone="accent">Sugestão de carga</SectionLabel>
        </div>

        <div class="relative flex items-end gap-4">
            <div class="flex flex-col gap-[5px]">
                <SectionLabel tone="secondary">Próxima série</SectionLabel>
                <div class="flex items-baseline gap-[7px]">
                    <span class="text-[60px] font-medium leading-[0.9] tracking-[-0.045em] text-text-primary [font-variant-numeric:tabular-nums]">{{ loadDisplay }}</span>
                    <span class="text-[17px] text-text-secondary">kg</span>
                    <template v-if="suggestedReps !== null">
                        <span class="text-[17px] text-text-tertiary">×</span>
                        <span class="text-[32px] font-medium leading-[0.9] tracking-[-0.03em] text-text-numeric [font-variant-numeric:tabular-nums]">{{ suggestedReps }}</span>
                    </template>
                </div>
            </div>
            <div v-if="previousDisplay" class="flex flex-1 flex-col items-end gap-[5px] pb-[5px]">
                <SectionLabel tone="secondary">Anterior</SectionLabel>
                <span class="text-[15px] text-text-secondary [font-variant-numeric:tabular-nums]">{{ previousDisplay }}</span>
            </div>
        </div>

        <p v-if="rationale" class="relative m-0 max-w-[300px] text-[13.5px] leading-[1.55] text-text-body">
            {{ rationale }}
        </p>

        <div class="relative flex gap-2.5">
            <BaseButton variant="primary" class="flex-1" @click="$emit('apply', { load: suggestedLoad, reps: suggestedReps })">
                Aplicar {{ loadDisplay }} kg
            </BaseButton>
            <BaseButton variant="secondary" icon-only aria-label="Dispensar sugestão" @click="$emit('dismiss')">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <path d="M4 4l8 8M12 4l-8 8" stroke="var(--text-secondary)" stroke-width="1.5" stroke-linecap="round" />
                </svg>
            </BaseButton>
        </div>
    </div>
</template>
