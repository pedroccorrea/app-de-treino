import { onUnmounted } from 'vue';
import { startAudioKeepAlive, stopAudioKeepAlive } from '@/Utils/audioAlert';
import { stopRestAlarm } from '@/Utils/restAlarm';

// "Arma" o alarme para um instante absoluto (timestamp de término do
// descanso), nunca para "daqui a N ticks". O setTimeout é só o gatilho
// rápido: se o navegador o congelar com a tela bloqueada, quem garante o
// disparo é o relógio da tela (`useNowTicker`), que ao voltar recalcula o
// tempo restante contra Date.now() e chama o mesmo `onElapsed` — por isso
// ele precisa ser idempotente.
export function useRestAlarm() {
    let timeoutId = null;

    const clearTimer = () => {
        if (timeoutId !== null) {
            clearTimeout(timeoutId);
            timeoutId = null;
        }
    };

    const armRestAlarm = (endsAt, onElapsed) => {
        clearTimer();
        // Mantém a página "tocando mídia" durante a espera (ver audioAlert.js).
        startAudioKeepAlive();
        timeoutId = setTimeout(() => {
            timeoutId = null;
            onElapsed();
        }, Math.max(0, endsAt - Date.now()));
    };

    const disarmRestAlarm = () => {
        clearTimer();
        stopAudioKeepAlive();
    };

    onUnmounted(() => {
        disarmRestAlarm();
        stopRestAlarm();
    });

    return { armRestAlarm, disarmRestAlarm };
}
