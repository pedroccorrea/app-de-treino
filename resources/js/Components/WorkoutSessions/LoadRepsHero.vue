<script setup>
defineProps({
    load: {
        type: [Number, String],
        default: '',
    },
    reps: {
        type: [Number, String],
        default: '',
    },
    lastText: {
        type: String,
        default: null,
    },
    deltaText: {
        type: String,
        default: null,
    },
});
</script>

<template>
    <div class="flex flex-col">
        <div class="flex items-start">
            <div class="flex flex-1 flex-col gap-1.5">
                <span class="text-[11px] uppercase tracking-[0.2em] text-[#8A8A99]">Carga</span>
                <div class="flex items-baseline gap-1.5">
                    <span
                        class="text-[82px] font-medium leading-[0.86] tracking-[-0.045em] text-white [font-variant-numeric:tabular-nums]"
                    >{{ load }}</span>
                    <span class="text-xl text-[#8A8A99]">kg</span>
                </div>
            </div>
            <div class="mx-[18px] mt-1.5 w-px self-stretch bg-gradient-to-b from-transparent via-white/10 to-transparent"></div>
            <div class="flex w-28 flex-col gap-1.5">
                <span class="text-[11px] uppercase tracking-[0.2em] text-[#8A8A99]">Reps</span>
                <div class="flex items-baseline gap-1.5">
                    <span
                        class="text-[82px] font-medium leading-[0.86] tracking-[-0.045em] text-white [font-variant-numeric:tabular-nums]"
                    >{{ reps }}</span>
                </div>
            </div>
        </div>

        <div v-if="lastText" class="mt-[22px] flex items-center gap-2.5">
            <span class="text-xs tracking-[0.03em] text-[#6E6E7E]">Última: {{ lastText }}</span>
            <span
                v-if="deltaText"
                class="rounded-full border border-[#8B5CF6]/[.28] bg-[#8B5CF6]/[.14] px-[9px] py-1 text-[11px] font-medium tracking-[0.08em] text-[#C4B5FD]"
            >{{ deltaText }}</span>
        </div>
    </div>
</template>
