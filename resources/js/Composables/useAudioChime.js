// Helper de áudio do cronômetro de descanso.
//
// Em vez de disparar o bipe no callback de um setInterval (que o navegador
// pode atrasar ou pausar quando a aba está em segundo plano / a tela está
// bloqueada), o bipe é agendado diretamente na linha do tempo de hardware
// do AudioContext (`ctx.currentTime + tempoRestante`). Esse agendamento é
// executado pela thread de áudio do navegador, independente da thread
// principal de UI, então o som toca no segundo exato mesmo com o app
// minimizado.
//
// Isso só funciona, porém, se o sistema operacional não suspender o
// processamento de áudio da página enquanto a tela está apagada — por
// isso, junto do agendamento, mantemos uma sessão de áudio contínua
// (ver `audioAlert.js`) tocando em loop até o apito soar.
import { startAudioKeepAlive, stopAudioKeepAlive } from '@/Utils/audioAlert';

let sharedContext = null;

function getAudioContext() {
    if (!sharedContext) {
        sharedContext = new (window.AudioContext || window.webkitAudioContext)();
    }
    if (sharedContext.state === 'suspended') {
        sharedContext.resume();
    }
    return sharedContext;
}

export function useAudioChime() {
    let scheduledNodes = [];
    let keepAliveTimeoutId = null;

    const clearKeepAliveTimeout = () => {
        if (keepAliveTimeoutId !== null) {
            clearTimeout(keepAliveTimeoutId);
            keepAliveTimeoutId = null;
        }
    };

    const cancelChime = () => {
        scheduledNodes.forEach(({ osc, gain }) => {
            try {
                osc.stop();
                osc.disconnect();
                gain.disconnect();
            } catch (_) {}
        });
        scheduledNodes = [];

        clearKeepAliveTimeout();
        stopAudioKeepAlive();
    };

    // Agenda o bipe de 3 notas para tocar daqui a `secondsFromNow` segundos,
    // calculado contra o relógio de hardware do AudioContext. Cancela
    // qualquer agendamento anterior (ex: quando o descanso é reiniciado ou
    // ganha tempo extra).
    const scheduleChime = (secondsFromNow = 0) => {
        cancelChime();

        // Mantém o canal de áudio acordado (loop de silêncio + Media Session)
        // durante toda a espera, para que o agendamento abaixo realmente
        // chegue a tocar mesmo com a tela apagada ou o app minimizado.
        startAudioKeepAlive();

        try {
            const ctx = getAudioContext();
            const startAt = ctx.currentTime + Math.max(0, secondsFromNow);

            [523, 659, 784].forEach((freq, i) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.frequency.value = freq;
                osc.type = 'sine';

                const noteStart = startAt + i * 0.15;
                gain.gain.setValueAtTime(0.3, noteStart);
                gain.gain.exponentialRampToValueAtTime(0.01, noteStart + 0.4);
                osc.start(noteStart);
                osc.stop(noteStart + 0.5);

                scheduledNodes.push({ osc, gain });
            });

            // Assim que a última nota do apito termina de tocar, o canal de
            // áudio pode voltar a ficar em silêncio — não é preciso manter o
            // loop de keep-alive além disso.
            const lastNoteEnd = startAt + 2 * 0.15 + 0.5;
            const delayMs = Math.max(0, (lastNoteEnd - ctx.currentTime) * 1000);
            keepAliveTimeoutId = setTimeout(() => {
                keepAliveTimeoutId = null;
                stopAudioKeepAlive();
            }, delayMs);
        } catch (_) {
            stopAudioKeepAlive();
        }
    };

    return { scheduleChime, cancelChime };
}
