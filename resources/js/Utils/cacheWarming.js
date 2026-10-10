// Aquecimento silencioso do cache offline: dispara, em segundo plano, uma
// requisição por ficha do usuário simulando uma navegação Inertia real
// (header `X-Inertia: true`). O Service Worker (`resources/pwa/sw.js`)
// intercepta essas requisições como faria com um clique do usuário e grava
// a resposta no `INERTIA_CACHE` — populando o dispositivo com o JSON de
// todas as fichas antes que o usuário abra cada uma manualmente.

const STAGGER_MS = 1500;
const IDLE_TIMEOUT_MS = 4000;

function scheduleIdle(callback) {
    if (typeof window === 'undefined') return;

    if (typeof window.requestIdleCallback === 'function') {
        window.requestIdleCallback(callback, { timeout: IDLE_TIMEOUT_MS });
        return;
    }

    setTimeout(callback, IDLE_TIMEOUT_MS);
}

async function warmWorkout(workoutId, version) {
    try {
        await fetch(`/workouts/${workoutId}`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Inertia': 'true',
                'X-Inertia-Version': version ?? '',
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'text/html, application/xhtml+xml',
            },
        });
    } catch (_) {
        // Sem conexão de verdade ou falha de rede: esta ficha simplesmente
        // não é aquecida agora. É silencioso por natureza — o usuário nunca
        // vê esse erro, e a próxima tentativa (próximo mount do layout) cobre.
    }
}

/**
 * Aquece o cache do Service Worker com o JSON do Inertia de cada ficha
 * ativa do usuário (`workoutIds`), para que abrir qualquer treino offline
 * funcione mesmo que aquele dispositivo nunca tenha visitado aquela página
 * antes. Roda em prioridade baixa (`requestIdleCallback`, com fallback por
 * `setTimeout`) e espalha as requisições no tempo para não competir com o
 * carregamento inicial da tela.
 */
export function warmWorkoutsCache(workoutIds, version) {
    if (!Array.isArray(workoutIds) || workoutIds.length === 0) return;
    if (typeof navigator !== 'undefined' && navigator.onLine === false) return;

    scheduleIdle(() => {
        workoutIds.forEach((workoutId, index) => {
            setTimeout(() => warmWorkout(workoutId, version), index * STAGGER_MS);
        });
    });
}

/**
 * Baixa, em segundo plano, os chunks JS das páginas de histórico
 * (Phase 44). O Service Worker guarda tudo de `/build/assets/` que passa
 * por ele, então depois disso a tela "Histórico indisponível offline" (ou a
 * cópia em cache) consegue renderizar sem conexão mesmo que o usuário nunca
 * tenha aberto o histórico neste aparelho — sem isso, o `import()` da
 * página falharia offline e o toque em "Histórico" não faria nada.
 */
export function warmHistoryPageChunks() {
    if (typeof navigator !== 'undefined' && navigator.onLine === false) return;

    scheduleIdle(() => {
        import('../Pages/WorkoutHistory/Index.vue').catch(() => {});
        import('../Pages/WorkoutHistory/Show.vue').catch(() => {});
    });
}
