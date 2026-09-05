import { onUnmounted } from 'vue';

// Mantém a tela acesa (Screen Wake Lock API) enquanto a sessão de treino
// está ativa. O sentinel é liberado automaticamente pelo navegador quando
// a aba fica oculta, então reagimos a `visibilitychange` para pedi-lo de
// volta assim que a tela volta a ficar visível.
export function useWakeLock() {
    let sentinel = null;
    let desired = false;

    const requestWakeLock = async () => {
        desired = true;
        if (!('wakeLock' in navigator) || document.visibilityState !== 'visible') return;

        try {
            sentinel = await navigator.wakeLock.request('screen');
            sentinel.addEventListener('release', () => {
                sentinel = null;
            });
        } catch (_) {
            sentinel = null;
        }
    };

    const releaseWakeLock = async () => {
        desired = false;
        if (sentinel) {
            try {
                await sentinel.release();
            } catch (_) {}
            sentinel = null;
        }
    };

    const handleVisibilityChange = () => {
        if (desired && !sentinel && document.visibilityState === 'visible') {
            requestWakeLock();
        }
    };

    document.addEventListener('visibilitychange', handleVisibilityChange);

    onUnmounted(() => {
        document.removeEventListener('visibilitychange', handleVisibilityChange);
        releaseWakeLock();
    });

    return { requestWakeLock, releaseWakeLock };
}
