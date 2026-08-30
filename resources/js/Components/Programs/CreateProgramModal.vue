<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    // The Inertia `useForm` instance owned by the parent page (Index).
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
                📋 Novo Programa de Treino
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Um programa agrupa suas fichas de treino de um ciclo (ex: uma
                periodização de hipertrofia de 8 semanas).
            </p>

            <div class="mt-4">
                <InputLabel for="program_name" value="Nome do programa" />
                <TextInput
                    id="program_name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full"
                    placeholder="Ex: Hipertrofia ABCD"
                />
                <InputError :message="form.errors.name" class="mt-2" />
            </div>

            <div class="mt-4">
                <InputLabel for="program_description" value="Descrição (opcional)" />
                <TextInput
                    id="program_description"
                    v-model="form.description"
                    type="text"
                    class="mt-1 block w-full"
                    placeholder="Ex: Foco em ganho de massa muscular"
                />
                <InputError :message="form.errors.description" class="mt-2" />
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
                    {{ form.processing ? 'Criando...' : 'Criar Programa' }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
