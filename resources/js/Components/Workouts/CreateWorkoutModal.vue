<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import WeekdaySelector from '@/Components/Workouts/WeekdaySelector.vue';

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    // The Inertia `useForm` instance owned by the parent page.
    form: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close', 'submit']);
</script>

<template>
    <Modal :show="show" @close="emit('close')">
        <div class="p-6">
            <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                🏋️‍♂️ Nova Ficha de Treino
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Depois de criar a ficha você poderá adicionar os exercícios na
                tela de edição.
            </p>

            <div class="mt-4">
                <InputLabel for="workout_name" value="Nome da ficha" />
                <TextInput
                    id="workout_name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full"
                    placeholder="Ex: Treino A - Peito e Tríceps"
                />
                <InputError :message="form.errors.name" class="mt-2" />
            </div>

            <div class="mt-4">
                <InputLabel for="workout_description" value="Descrição (opcional)" />
                <TextInput
                    id="workout_description"
                    v-model="form.description"
                    type="text"
                    class="mt-1 block w-full"
                    placeholder="Ex: Foco em hipertrofia"
                />
                <InputError :message="form.errors.description" class="mt-2" />
            </div>

            <div class="mt-4">
                <InputLabel value="Dias da semana (opcional)" />
                <WeekdaySelector v-model="form.days_of_week" class="mt-2" />
                <InputError :message="form.errors.days_of_week" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton :disabled="form.processing" @click="emit('close')">
                    Cancelar
                </SecondaryButton>
                <PrimaryButton
                    :disabled="form.processing"
                    :class="{ 'opacity-50': form.processing }"
                    @click="emit('submit')"
                >
                    {{ form.processing ? 'Criando...' : 'Criar Ficha' }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
