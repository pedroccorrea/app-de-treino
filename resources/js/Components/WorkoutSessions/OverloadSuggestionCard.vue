<script setup>
import { computed } from 'vue';

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
        class="relative flex flex-col gap-4 overflow-hidden rounded-[22px] border border-[#8B5CF6]/[.26] p-5 pb-[18px] shadow-[0_24px_60px_rgba(0,0,0,0.5),inset_0_1px_0_rgba(255,255,255,0.04)]"
        style="background: linear-gradient(165deg, #191428 0%, #141420 55%, #12121a 100%)"
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
                stroke="rgba(139,92,246,.35)"
                stroke-width="1.2"
            />
            <path
                d="M0 76C30 76 40 38 70 38s40 30 70 30 40-42 70-42 40 34 70 34 40-30 70-30"
                stroke="rgba(139,92,246,.16)"
                stroke-width="1.2"
            />
        </svg>

        <div class="relative flex items-center gap-2.5">
            <svg width="22" height="10" viewBox="0 0 22 10" fill="none">
                <path
                    d="M1 5C3 1.4 5 1.4 7 5s4 3.6 6 0 4-3.6 6 0"
                    stroke="#A78BFA"
                    stroke-width="1.5"
                    stroke-linecap="round"
                />
            </svg>
            <span class="text-[11px] uppercase tracking-[0.2em] text-[#A78BFA]">Sugestão de carga</span>
        </div>

        <div class="relative flex items-end gap-4">
            <div class="flex flex-col gap-[5px]">
                <span class="text-[11px] uppercase tracking-[0.18em] text-[#7E7E90]">Próxima série</span>
                <div class="flex items-baseline gap-[7px]">
                    <span class="text-[60px] font-medium leading-[0.9] tracking-[-0.045em] text-white [font-variant-numeric:tabular-nums]">{{ loadDisplay }}</span>
                    <span class="text-[17px] text-[#8A8A99]">kg</span>
                    <template v-if="suggestedReps !== null">
                        <span class="text-[17px] text-[#4E4E5C]">×</span>
                        <span class="text-[32px] font-medium leading-[0.9] tracking-[-0.03em] text-[#E4E4EA] [font-variant-numeric:tabular-nums]">{{ suggestedReps }}</span>
                    </template>
                </div>
            </div>
            <div v-if="previousDisplay" class="flex flex-1 flex-col items-end gap-[5px] pb-[5px]">
                <span class="text-[11px] uppercase tracking-[0.14em] text-[#7E7E90]">Anterior</span>
                <span class="text-[15px] text-[#8A8A99] [font-variant-numeric:tabular-nums]">{{ previousDisplay }}</span>
            </div>
        </div>

        <p v-if="rationale" class="relative m-0 max-w-[300px] text-[13.5px] leading-[1.55] text-[#A9A9B8]">
            {{ rationale }}
        </p>

        <div class="relative flex gap-2.5">
            <button
                type="button"
                @click="$emit('apply', { load: suggestedLoad, reps: suggestedReps })"
                class="flex h-[52px] flex-1 items-center justify-center rounded-[15px] border border-[#8B5CF6] bg-[#8B5CF6]/[.12] text-[15px] font-medium text-[#EDE9FE] transition hover:bg-[#8B5CF6]/20 active:bg-[#8B5CF6]/[.28]"
            >
                Aplicar {{ loadDisplay }} kg
            </button>
            <button
                type="button"
                aria-label="Dispensar sugestão"
                @click="$emit('dismiss')"
                class="flex h-[52px] w-[52px] items-center justify-center rounded-[15px] border border-white/10 transition hover:border-white/[.22]"
            >
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <path d="M4 4l8 8M12 4l-8 8" stroke="#8A8A99" stroke-width="1.5" stroke-linecap="round" />
                </svg>
            </button>
        </div>
    </div>
</template>
