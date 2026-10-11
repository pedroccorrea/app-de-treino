<script setup>
import BaseButton from '@/Components/UI/BaseButton.vue';
import SectionLabel from '@/Components/UI/SectionLabel.vue';
import { isAppleMobileDevice } from '@/Utils/appleMobileDevice';
import { playRestAlarm } from '@/Utils/restAlarm';
import { getKeepScreenOn, isWakeLockSupported, setKeepScreenOn } from '@/Utils/workoutPreferences';
import { ref } from 'vue';

// Navegador sem Screen Wake Lock API (ex: iOS < 16.4): a opção some.
const wakeLockSupported = isWakeLockSupported();
const keepScreenOn = ref(getKeepScreenOn());
// iPhone/iPad: o app não toca áudio no treino (ver `Utils/restAlarm.js`).
const isAppleMobile = isAppleMobileDevice();

const toggleKeepScreenOn = () => {
    keepScreenOn.value = !keepScreenOn.value;
    setKeepScreenOn(keepScreenOn.value);
};
</script>

<template>
    <section class="space-y-5">
        <header>
            <SectionLabel tone="accent">Treino</SectionLabel>
            <p class="mt-1 text-sm text-text-body">
                Preferências salvas neste aparelho.
            </p>
        </header>

        <div v-if="wakeLockSupported" class="flex items-center justify-between gap-4">
            <div>
                <p id="keep-screen-on-label" class="text-[15px] text-text-primary">
                    Manter tela ligada durante o treino
                </p>
                <p class="mt-0.5 text-xs text-text-tertiary">
                    Evita que a tela apague enquanto um treino estiver aberto.
                </p>
            </div>

            <button
                type="button"
                role="switch"
                :aria-checked="keepScreenOn"
                aria-labelledby="keep-screen-on-label"
                @click="toggleKeepScreenOn"
                :class="[
                    'relative inline-flex h-8 w-14 shrink-0 items-center rounded-radius-full border transition',
                    keepScreenOn ? 'border-border-accent bg-accent-muted' : 'border-border-subtle bg-surface-overlay',
                ]"
            >
                <span
                    :class="[
                        'inline-block h-6 w-6 rounded-radius-full transition-transform',
                        keepScreenOn ? 'translate-x-7 bg-accent' : 'translate-x-1 bg-text-secondary',
                    ]"
                />
            </button>
        </div>

        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-[15px] text-text-primary">Alarme do descanso</p>
                <p v-if="isAppleMobile" class="mt-0.5 text-xs text-text-tertiary">
                    No iPhone, o fim do descanso é avisado só na tela.
                </p>
                <p v-else class="mt-0.5 text-xs text-text-tertiary">
                    Confira o volume antes de treinar.
                </p>
            </div>

            <BaseButton v-if="!isAppleMobile" variant="secondary" class="shrink-0" @click="playRestAlarm">
                Testar alarme
            </BaseButton>
        </div>
    </section>
</template>
