<script setup>
import InputError from '@/Components/InputError.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import LoadRepsHero from '@/Components/WorkoutSessions/LoadRepsHero.vue';
import LoadRepsStepper from '@/Components/WorkoutSessions/LoadRepsStepper.vue';
import { DEFAULT_LOAD_INCREMENT, stepLoad } from '@/Utils/loadSteps';
import { computed, ref, watch } from 'vue';

// Editor de UMA série (carga e repetições), sem saber de onde ela vem: o
// histórico (Phase 45) e o treino em andamento (Phase 46) entregam os
// valores atuais por props e recebem o resultado pelo evento `save`. Quem
// usa decide onde mostrar (modal, drawer) e como salvar — este componente
// nunca chama o backend.
//
// Usa os mesmos controles da tela de treino: o número grande com toque para
// digitar (`LoadRepsHero`), os botões +/− (`LoadRepsStepper`) e o passo de
// carga por equipamento (`stepLoad`, Utils/loadSteps.js).
const props = defineProps({
    // Carga atual em kg; null = série sem carga (exibida como "—kg").
    weight: {
        type: Number,
        default: null,
    },
    reps: {
        type: Number,
        required: true,
    },
    // Equipamento do exercício (Exercise::equipment): define o passo do +/−.
    equipment: {
        type: String,
        default: null,
    },
    processing: {
        type: Boolean,
        default: false,
    },
    // Erros de validação do backend ({ weight, reps }), ex: `form.errors`.
    errors: {
        type: Object,
        default: () => ({}),
    },
    saveText: {
        type: String,
        default: 'Salvar série',
    },
});

const emit = defineEmits(['save', 'cancel']);

// Rascunho local: nada muda no pai até "Salvar". Se o pai trocar a série
// editada (mesmo componente reaproveitado), o rascunho recomeça dela.
const draftWeight = ref(props.weight);
const draftReps = ref(props.reps);

watch(
    () => [props.weight, props.reps],
    ([weight, reps]) => {
        draftWeight.value = weight;
        draftReps.value = reps;
    },
);

const hasLoad = computed(() => draftWeight.value !== null);

const fmt = (n) => Number(n).toLocaleString('pt-BR', { maximumFractionDigits: 2 });

// Mesmo rótulo do treino em andamento ("Carga · 2,5"), calculado a partir do
// passo real do equipamento para a carga atual.
const loadStepLabel = computed(() => {
    const current = draftWeight.value ?? 0;
    const delta = stepLoad(props.equipment, current, 1) - current;

    return `Carga · ${fmt(delta || DEFAULT_LOAD_INCREMENT)}`;
});

const adjustWeight = (direction) => {
    // Sem carga e tocando −: continua sem carga (não vira "0 kg" sozinho).
    if (!hasLoad.value && direction < 0) return;

    draftWeight.value = stepLoad(props.equipment, draftWeight.value ?? 0, direction);
};

const adjustReps = (delta) => {
    draftReps.value = Math.max(1, (Number(draftReps.value) || 0) + delta);
};

const setWeight = (value) => {
    draftWeight.value = Math.max(0, Math.round(value * 100) / 100);
};

const setReps = (value) => {
    draftReps.value = Math.max(1, Math.round(value));
};

const toggleLoad = () => {
    draftWeight.value = hasLoad.value ? null : 0;
};

const save = () => {
    emit('save', { weight: draftWeight.value, reps: draftReps.value });
};
</script>

<template>
    <div class="flex flex-col gap-5">
        <LoadRepsHero
            :load="hasLoad ? draftWeight : '—'"
            :reps="draftReps"
            @update:load="setWeight"
            @update:reps="setReps"
        />

        <div class="flex gap-2.5">
            <LoadRepsStepper :label="loadStepLabel" @decrement="adjustWeight(-1)" @increment="adjustWeight(1)" />
            <LoadRepsStepper label="Reps · 1" @decrement="adjustReps(-1)" @increment="adjustReps(1)" />
        </div>

        <BaseButton variant="ghost" class="self-start !px-0" :disabled="processing" @click="toggleLoad">
            {{ hasLoad ? 'Marcar como sem carga' : 'Adicionar carga' }}
        </BaseButton>

        <div v-if="errors.weight || errors.reps" class="flex flex-col gap-1">
            <InputError :message="errors.weight" />
            <InputError :message="errors.reps" />
        </div>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <BaseButton variant="secondary" :disabled="processing" @click="emit('cancel')">Cancelar</BaseButton>
            <BaseButton :disabled="processing" @click="save">
                {{ processing ? 'Salvando...' : saveText }}
            </BaseButton>
        </div>
    </div>
</template>
