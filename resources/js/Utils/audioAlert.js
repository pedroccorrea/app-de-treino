// Utilitário de "sessão de áudio contínua" para o cronômetro de descanso.
//
// Android e iOS suspendem o processamento de áudio da página assim que a
// tela é apagada ou o app é minimizado, a menos que exista uma sessão de
// mídia ativa e conhecida do sistema operacional. Um elemento <audio loop>
// tocando uma faixa de silêncio, anunciado via Media Session API, mantém
// esse canal "acordado" durante toda a contagem — é o que garante que o
// apito agendado em `useAudioChime.js` realmente chegue a soar no segundo
// zero, mesmo com o celular no bolso e a tela bloqueada.

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
    const audio = getSilentAudioElement();
    updateMediaSession('playing');
    audio.play().catch(() => {});
}

// Pausa o loop de silêncio — chamado logo após o apito de alerta soar,
// já que a sessão de áudio não precisa mais ficar acordada.
export function stopAudioKeepAlive() {
    if (!silentAudioEl) return;
    silentAudioEl.pause();
    silentAudioEl.currentTime = 0;
    updateMediaSession('paused');
}
