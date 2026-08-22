<script setup>
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    // The Inertia `useForm` instance owned by the parent page (Index).
    // Mutated directly here, the same way a v-model target would be.
    form: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close', 'submit']);

const fileInput = ref(null);
const previewUrl = ref(null);
const hasImage = computed(() => props.form.image !== null);

const resetPreview = () => {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = null;
    }

    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const handleFileChange = (event) => {
    const file = event.target.files[0] ?? null;
    props.form.image = file;

    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }

    previewUrl.value = file ? URL.createObjectURL(file) : null;
};

const closeModal = () => {
    resetPreview();
    emit('close');
};

defineExpose({ resetPreview });
</script>

<template>
    <Modal :show="show" @close="closeModal">
        <div class="p-6">
            <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                📷 Escanear Ficha de Treino
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Envie uma foto da sua ficha de treino em papel e a IA vai
                identificar os exercícios, séries e repetições
                automaticamente.
            </p>

            <div class="mt-4">
                <input
                    ref="fileInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="block w-full cursor-pointer text-sm text-gray-600 file:mr-4 file:cursor-pointer file:rounded-lg file:border-0 file:bg-violet-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-violet-700 hover:file:bg-violet-100 dark:text-gray-400 dark:file:bg-violet-500/10 dark:file:text-violet-400"
                    @change="handleFileChange"
                />
                <InputError :message="form.errors.image" class="mt-2" />
            </div>

            <div v-if="previewUrl" class="mt-4">
                <img
                    :src="previewUrl"
                    alt="Pré-visualização da ficha de treino"
                    class="max-h-64 w-full rounded-xl border border-gray-200 object-contain dark:border-gray-700"
                />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton :disabled="form.processing" @click="closeModal">
                    Cancelar
                </SecondaryButton>
                <PrimaryButton
                    :disabled="!hasImage || form.processing"
                    :class="{ 'opacity-50': !hasImage || form.processing }"
                    @click="emit('submit')"
                >
                    {{
                        form.processing
                            ? 'Escaneando...'
                            : 'Escanear e Importar'
                    }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
