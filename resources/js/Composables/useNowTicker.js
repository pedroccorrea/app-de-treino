import { onMounted, onUnmounted, ref } from 'vue';

// Cronômetro compartilhado baseado em timestamp: em vez de incrementar um
// contador a cada tick (que atrasa quando a aba fica em segundo plano e o
// navegador limita o setInterval), expõe um `now` que é recalculado a
// partir de Date.now(). Qualquer cronômetro derivado deste `now` volta a
// mostrar o valor correto instantaneamente ao religar a tela, pois o delta
// é recomputado contra o timestamp real, não contra ticks perdidos.
export function useNowTicker(intervalMs = 1000) {
    const now = ref(Date.now());
    let intervalId = null;

    const tick = () => {
        now.value = Date.now();
    };

    const handleVisibilityChange = () => {
        if (document.visibilityState === 'visible') tick();
    };

    onMounted(() => {
        intervalId = setInterval(tick, intervalMs);
        document.addEventListener('visibilitychange', handleVisibilityChange);
        window.addEventListener('focus', tick);
    });

    onUnmounted(() => {
        clearInterval(intervalId);
        document.removeEventListener('visibilitychange', handleVisibilityChange);
        window.removeEventListener('focus', tick);
    });

    return { now, tick };
}
