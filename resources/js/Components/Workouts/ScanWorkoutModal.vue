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

// Max width (px) and JPEG quality used to downscale photos taken on a phone
// camera before upload. Calibrated at 800px/70% (<100KB) to ensure the
// image is small enough for instant upload without cURL timeouts, while
// still being large enough for Gemini Vision to read text on workout sheets.
const MAX_WIDTH = 800;
const JPEG_QUALITY = 0.70;

const cameraInput = ref(null);
const galleryInput = ref(null);
const previewUrl = ref(null);
const isCompressing = ref(false);
const hasImage = computed(() => props.form.image !== null);

const resetPreview = () => {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = null;
    }

    if (cameraInput.value) cameraInput.value.value = '';
    if (galleryInput.value) galleryInput.value.value = '';
};

/**
 * Redraws the image onto an off-screen canvas capped at MAX_WIDTH and
 * re-encodes it as JPEG at JPEG_QUALITY, so large phone-camera photos are
 * resized client-side instead of hitting the backend at full resolution.
 */
const compressImage = (file) =>
    new Promise((resolve, reject) => {
        const objectUrl = URL.createObjectURL(file);
        const img = new Image();

        img.onload = () => {
            URL.revokeObjectURL(objectUrl);

            let { width, height } = img;
            if (width > MAX_WIDTH) {
                height = Math.round((height * MAX_WIDTH) / width);
                width = MAX_WIDTH;
            }

            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;

            const context = canvas.getContext('2d');
            if (!context) {
                reject(new Error('Canvas indisponível.'));
                return;
            }
            context.imageSmoothingQuality = 'high';
            context.drawImage(img, 0, 0, width, height);

            canvas.toBlob(
                (blob) => {
                    if (!blob) {
                        reject(new Error('Falha ao comprimir a imagem.'));
                        return;
                    }

                    resolve(
                        new File(
                            [blob],
                            file.name.replace(/\.\w+$/, '.jpg'),
                            { type: 'image/jpeg' },
                        ),
                    );
                },
                'image/jpeg',
                JPEG_QUALITY,
            );
        };

        img.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            reject(new Error('Não foi possível carregar a imagem selecionada.'));
        };

        img.src = objectUrl;
    });

const setImage = (file) => {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }

    props.form.image = file;
    previewUrl.value = file ? URL.createObjectURL(file) : null;
};

const handleFileChange = async (event) => {
    const file = event.target.files[0] ?? null;

    if (!file) {
        setImage(null);
        return;
    }

    isCompressing.value = true;

    try {
        const compressed = await compressImage(file);
        setImage(compressed);
    } catch {
        // If compression fails for any reason (unsupported format, no
        // canvas support, corrupt file) fall back to the original file
        // rather than blocking the user from submitting.
        setImage(file);
    } finally {
        isCompressing.value = false;
    }
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

            <div class="mt-4 grid grid-cols-2 gap-3">
                <input
                    ref="cameraInput"
                    type="file"
                    accept="image/*"
                    capture="environment"
                    class="hidden"
                    @change="handleFileChange"
                />
                <input
                    ref="galleryInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="hidden"
                    @change="handleFileChange"
                />

                <button
                    type="button"
                    class="flex flex-col items-center justify-center gap-1 rounded-xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm font-semibold text-violet-700 transition hover:bg-violet-100 dark:border-violet-500/20 dark:bg-violet-500/10 dark:text-violet-400 dark:hover:bg-violet-500/20"
                    @click="cameraInput?.click()"
                >
                    <span class="text-xl">📸</span>
                    Tirar Foto
                </button>

                <button
                    type="button"
                    class="flex flex-col items-center justify-center gap-1 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-700 transition hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-700/40 dark:text-gray-300 dark:hover:bg-gray-700"
                    @click="galleryInput?.click()"
                >
                    <span class="text-xl">📁</span>
                    Escolher da Galeria
                </button>

                <InputError :message="form.errors.image" class="col-span-2 mt-1" />
            </div>

            <p v-if="isCompressing" class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                Otimizando imagem...
            </p>

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
                    :disabled="!hasImage || isCompressing || form.processing"
                    :class="{ 'opacity-50': !hasImage || isCompressing || form.processing }"
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
