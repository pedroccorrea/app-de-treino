import axios from 'axios';
window.axios = axios;

// O Service Worker responde com um JSON de fallback sinalizado por este
// cabeçalho quando uma navegação/requisição Inertia falha offline e não há
// nenhuma cópia em cache daquela URL exata (ver `resources/pwa/sw.js`). Em
// vez de deixar o Inertia tentar renderizar esse JSON vazio como se fosse uma
// página de verdade, recarregamos: o reload é uma navegação de documento
// completo, que o Service Worker sabe resolver com o app shell em cache.
const OFFLINE_RELOAD_KEY = 'setwave:offline-reload-at';
const OFFLINE_RELOAD_COOLDOWN_MS = 10000;

/**
 * Um reload só resolve o problema quando a navegação de documento completa
 * tem como ser servida. Se ela também cair no fallback, o par
 * "fallback → reload" vira um laço infinito que consome bateria e trava a
 * tela. O carimbo abaixo garante no máximo um reload a cada 10 segundos.
 */
function shouldReloadForOfflineFallback() {
    try {
        const last = Number(window.sessionStorage.getItem(OFFLINE_RELOAD_KEY) ?? 0);

        if (Date.now() - last < OFFLINE_RELOAD_COOLDOWN_MS) return false;

        window.sessionStorage.setItem(OFFLINE_RELOAD_KEY, String(Date.now()));

        return true;
    } catch (_) {
        // sessionStorage indisponível (modo privado antigo): sem carimbo
        // possível, o comportamento anterior (recarregar) é mantido.
        return true;
    }
}

axios.interceptors.response.use((response) => {
    if (response.headers?.['x-inertia-offline-fallback']) {
        if (shouldReloadForOfflineFallback()) {
            window.location.reload();
        }

        return new Promise(() => {});
    }

    return response;
});