// Preferências de treino DESTE aparelho (não do usuário no servidor): manter
// a tela ligada só faz sentido no celular em que se está treinando, então
// fica no localStorage — sem migration, sem rota, funciona offline.
const KEEP_SCREEN_ON_KEY = 'setwave:keep-screen-on';

export const isWakeLockSupported = () =>
    typeof navigator !== 'undefined' && 'wakeLock' in navigator;

export function getKeepScreenOn() {
    try {
        return localStorage.getItem(KEEP_SCREEN_ON_KEY) === '1';
    } catch (_) {
        return false;
    }
}

export function setKeepScreenOn(enabled) {
    try {
        localStorage.setItem(KEEP_SCREEN_ON_KEY, enabled ? '1' : '0');
    } catch (_) {}
}
