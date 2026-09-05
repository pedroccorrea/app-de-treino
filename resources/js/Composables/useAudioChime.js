// Helper de áudio do cronômetro de descanso.
//
// Em vez de disparar o bipe no callback de um setInterval (que o navegador
// pode atrasar ou pausar quando a aba está em segundo plano / a tela está
// bloqueada), o bipe é agendado diretamente na linha do tempo de hardware
// do AudioContext (`ctx.currentTime + tempoRestante`). Esse agendamento é
// executado pela thread de áudio do navegador, independente da thread
// principal de UI, então o som toca no segundo exato mesmo com o app
// minimizado.
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

    const cancelChime = () => {
        scheduledNodes.forEach(({ osc, gain }) => {
            try {
                osc.stop();
                osc.disconnect();
                gain.disconnect();
            } catch (_) {}
        });
        scheduledNodes = [];
    };

    // Agenda o bipe de 3 notas para tocar daqui a `secondsFromNow` segundos,
    // calculado contra o relógio de hardware do AudioContext. Cancela
    // qualquer agendamento anterior (ex: quando o descanso é reiniciado ou
    // ganha tempo extra).
    const scheduleChime = (secondsFromNow = 0) => {
        cancelChime();

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
        } catch (_) {}
    };

    return { scheduleChime, cancelChime };
}
