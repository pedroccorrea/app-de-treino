<script setup>
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseCard from '@/Components/UI/BaseCard.vue';
import { formatSet, formatVolume } from '@/Utils/historyFormat';
import { Link } from '@inertiajs/vue3';

defineProps({
    // { exercise_id, name, equipment, volume, sets: [{ id, set_number, weight, reps, is_warmup }] }
    exercise: {
        type: Object,
        required: true,
    },
    // Link para o histórico por exercício que já existe (`exercises.history`).
    historyHref: {
        type: String,
        required: true,
    },
    // Phase 45: mostra os controles de correção (tocar na série para editar,
    // lixeira para excluir o exercício deste treino). A página decide o que
    // fazer com cada pedido.
    editable: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['edit-set', 'delete']);
</script>

<template>
    <BaseCard>
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h3 class="text-base font-semibold text-text-primary">{{ exercise.name }}</h3>
                <p v-if="exercise.volume > 0" class="mt-0.5 text-xs text-text-tertiary [font-variant-numeric:tabular-nums]">
                    Volume {{ formatVolume(exercise.volume) }}
                </p>
            </div>

            <div class="flex shrink-0 items-center">
                <Link
                    :href="historyHref"
                    class="inline-flex min-h-[48px] items-center gap-1 rounded-radius-md px-2 text-xs font-medium text-text-secondary transition hover:text-accent-text-soft"
                >
                    Evolução
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </Link>

                <BaseButton
                    v-if="editable"
                    variant="ghost"
                    icon-only
                    class="hover:!text-danger"
                    :aria-label="`Excluir ${exercise.name} deste treino`"
                    @click="emit('delete')"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M10 11v6M14 11v6M5 7l1 12a2 2 0 002 2h8a2 2 0 002-2l1-12M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3" />
                    </svg>
                </BaseButton>
            </div>
        </div>

        <ol class="mt-3 space-y-1.5">
            <li v-for="set in exercise.sets" :key="set.id">
                <component
                    :is="editable ? 'button' : 'div'"
                    :type="editable ? 'button' : undefined"
                    :aria-label="editable ? `Editar série ${set.set_number}: ${formatSet(set)}` : undefined"
                    class="flex w-full items-center justify-between gap-3 rounded-radius-sm border border-border-subtle bg-surface-overlay/40 px-3 text-left"
                    :class="editable ? 'min-h-[48px] transition hover:border-border-accent active:bg-surface-overlay' : 'py-2'"
                    @click="editable && emit('edit-set', set)"
                >
                    <span class="text-xs text-text-secondary">
                        Série {{ set.set_number }}
                        <span v-if="set.is_warmup" class="text-text-tertiary">· aquecimento</span>
                    </span>
                    <span class="flex items-center gap-2 text-sm font-medium text-text-primary [font-variant-numeric:tabular-nums]">
                        {{ formatSet(set) }}
                        <svg v-if="editable" class="h-4 w-4 text-text-tertiary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 13l6.232-6.232a2.5 2.5 0 113.536 3.536L12.536 16.536 9 17l.464-3.536z" />
                        </svg>
                    </span>
                </component>
            </li>
        </ol>
    </BaseCard>
</template>
