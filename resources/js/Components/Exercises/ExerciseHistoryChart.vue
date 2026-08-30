<script setup>
import { computed } from 'vue';

const props = defineProps({
    series: {
        // [{ session_id, date, volume }], em ordem cronológica
        type: Array,
        required: true,
    },
});

const PAD_X = 12;
const PAD_TOP = 16;
const BASELINE = 112;
const VIEW_W = 320;
const VIEW_H = 140;

const formatDate = (isoDate) =>
    new Date(`${isoDate}T00:00:00`).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });

const fmtVolume = (n) => Number(n).toLocaleString('pt-BR', { maximumFractionDigits: 0 });

const points = computed(() => {
    const values = props.series.map((point) => point.volume);
    const min = Math.min(...values);
    const max = Math.max(...values);
    const span = max - min || 1;
    const innerWidth = VIEW_W - PAD_X * 2;
    const count = props.series.length;

    return props.series.map((point, index) => {
        const x = count > 1 ? PAD_X + (innerWidth * index) / (count - 1) : VIEW_W / 2;
        const y = BASELINE - ((point.volume - min) / span) * (BASELINE - PAD_TOP);
        return { x, y, ...point };
    });
});

const linePath = computed(() =>
    points.value.map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x.toFixed(1)} ${p.y.toFixed(1)}`).join(' '),
);

const areaPath = computed(() => {
    if (points.value.length === 0) return '';
    const first = points.value[0];
    const last = points.value[points.value.length - 1];
    return `${linePath.value} L${last.x.toFixed(1)} ${BASELINE} L${first.x.toFixed(1)} ${BASELINE} Z`;
});

const lastPoint = computed(() => points.value[points.value.length - 1] ?? null);
const firstPoint = computed(() => points.value[0] ?? null);
</script>

<template>
    <div v-if="series.length === 0" class="rounded-radius-md border border-dashed border-border-subtle px-4 py-10 text-center">
        <p class="text-sm text-text-secondary">
            Ainda não há sessões registradas para este exercício neste período.
        </p>
    </div>

    <div v-else class="flex flex-col gap-2">
        <svg
            :viewBox="`0 0 ${VIEW_W} ${VIEW_H}`"
            class="block w-full"
            preserveAspectRatio="none"
            aria-hidden="true"
        >
            <defs>
                <linearGradient id="history-chart-fill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="var(--accent)" stop-opacity="0.22" />
                    <stop offset="100%" stop-color="var(--accent)" stop-opacity="0" />
                </linearGradient>
            </defs>

            <line :x1="PAD_X" :y1="BASELINE" :x2="VIEW_W - PAD_X" :y2="BASELINE" stroke="rgba(255,255,255,.08)" stroke-width="1" />

            <path v-if="points.length > 1" :d="areaPath" fill="url(#history-chart-fill)" stroke="none" />

            <path
                v-if="points.length > 1"
                :d="linePath"
                fill="none"
                stroke="var(--accent)"
                stroke-width="2.2"
                stroke-linecap="round"
                stroke-linejoin="round"
                style="filter: drop-shadow(0 0 8px var(--accent-glow))"
            />

            <circle
                v-for="(point, index) in points"
                :key="point.session_id"
                :cx="point.x"
                :cy="point.y"
                :r="index === points.length - 1 ? 4 : 2.5"
                fill="var(--accent)"
            />
        </svg>

        <div class="flex items-center justify-between px-0.5 text-[11px] text-text-tertiary">
            <span v-if="firstPoint">{{ formatDate(firstPoint.date) }} · {{ fmtVolume(firstPoint.volume) }} kg</span>
            <span v-if="lastPoint && lastPoint !== firstPoint">{{ formatDate(lastPoint.date) }} · {{ fmtVolume(lastPoint.volume) }} kg</span>
        </div>
    </div>
</template>
