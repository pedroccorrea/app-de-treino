// Ponte de comunicação com o Service Worker (Phase 36): sempre que uma
// sessão de treino é iniciada, alterada (série registrada) ou finalizada,
// o frontend avisa o Service Worker para expurgar imediatamente as páginas
// Inertia cacheadas (`/dashboard`, `/workouts*`, `/programs*`) do
// `INERTIA_CACHE` (ver listener `message` em `resources/pwa/sw.js`). Sem
// isso, a estratégia `networkFirst` do Service Worker poderia continuar
// servindo, em modo offline, uma resposta JSON antiga com o estado de
// sessão já ultrapassado.

/**
 * Pede ao Service Worker ativo para expurgar as páginas Inertia cacheadas
 * relacionadas a sessões de treino. Silencioso por natureza: sem Service
 * Worker registrado (navegador sem suporte, ou script ainda não assumiu o
 * controle da página), não há nada para expurgar.
 *
 * `includeHistory` (Phase 45): uma correção no histórico (série editada,
 * exercício ou treino excluído) também deixa velhas as cópias do próprio
 * histórico (`/history*`) e da evolução por exercício (`/exercises/*`), que
 * o Service Worker prefere servir do cache quando offline.
 */
export function evictInertiaCache({ includeHistory = false } = {}) {
    if (typeof navigator === 'undefined' || !('serviceWorker' in navigator)) return;

    navigator.serviceWorker.controller?.postMessage({ type: 'EVICT_INERTIA_CACHE', includeHistory });
}
