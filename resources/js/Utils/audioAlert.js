// Utilitário de "sessão de áudio contínua" para o cronômetro de descanso.
//
// Android e iOS suspendem o processamento de áudio da página assim que a
// tela é apagada ou o app é minimizado, a menos que exista uma sessão de
// mídia ativa e conhecida do sistema operacional. Um elemento <audio loop>
// tocando uma faixa de silêncio, anunciado via Media Session API, mantém
// esse canal "acordado" durante toda a contagem — é o que garante que o
// alarme agendado em `useRestAlarm.js` realmente chegue a soar no segundo
// zero, mesmo com o celular no bolso e a tela bloqueada.
//
// Hoje o alarme em si é um <audio> (ver `restAlarm.js`); este loop continua
// servindo para manter a página "tocando mídia" durante a contagem, o que no
// Android evita que o navegador congele o setTimeout que dispara o alarme.
//
// iPhone/iPad (Phase 50): nada aqui toca — no iOS, até o loop de silêncio
// interrompe a música de outros apps (Spotify, Apple Music). O fim do
// descanso é avisado só na tela; o elemento <audio> nem chega a ser criado.
import { isAppleMobileDevice } from './appleMobileDevice.js';

// WAV de 0,1s de silêncio (PCM 8-bit mono, 8kHz) — pequeno o bastante para
// embutir como data URI, sem depender de um arquivo de asset separado.
const SILENT_TRACK_SRC =
    'data:audio/wav;base64,UklGRkQDAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YSADAACAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgA==';

let silentAudioEl = null;

function getSilentAudioElement() {
    if (!silentAudioEl) {
        silentAudioEl = new Audio(SILENT_TRACK_SRC);
        silentAudioEl.loop = true;
        silentAudioEl.preload = 'auto';
        // Volume baixo, mas não zero — alguns navegadores tratam uma faixa
        // com volume 0 como pausada e suspendem o processamento mesmo assim.
        silentAudioEl.volume = 0.01;
    }
    return silentAudioEl;
}

function updateMediaSession(playbackState) {
    if (!('mediaSession' in navigator)) return;

    navigator.mediaSession.metadata = new MediaMetadata({
        title: 'Descanso em andamento',
        artist: 'Setwave',
        album: 'Cronômetro de descanso',
    });
    navigator.mediaSession.playbackState = playbackState;
}

// Inicia o loop de silêncio, abrindo/mantendo a sessão de áudio ativa.
// Chamado ao iniciar (ou estender) a contagem regressiva de descanso.
export function startAudioKeepAlive() {
    if (isAppleMobileDevice()) return;

    const audio = getSilentAudioElement();
    updateMediaSession('playing');
    audio.play().catch(() => {});
}

// "Desbloqueia" o elemento do loop de silêncio: precisa rodar DENTRO de um
// toque do usuário. Celulares só deixam um <audio> tocar sozinho depois que
// ele tocou uma vez a partir de um gesto — e `startAudioKeepAlive()` roda
// depois de uma ida ao servidor, fora do gesto.
export function unlockAudioKeepAlive() {
    if (isAppleMobileDevice()) return;

    const audio = getSilentAudioElement();
    if (!audio.paused) return;

    audio.play()
        .then(() => {
            audio.pause();
            audio.currentTime = 0;
        })
        .catch(() => {});
}

// Pausa o loop de silêncio — chamado logo antes do alarme tocar,
// já que a sessão de áudio não precisa mais ficar acordada. No iPhone/iPad o
// elemento nunca é criado, então sai sem mexer na Media Session.
export function stopAudioKeepAlive() {
    if (!silentAudioEl) return;
    silentAudioEl.pause();
    silentAudioEl.currentTime = 0;
    updateMediaSession('paused');
}
