// Alarme de fim do descanso.
//
// Por que <audio> e não Web Audio: no iPhone, com a chave de silencioso
// ligada, o AudioContext fica mudo — um elemento <audio> de mídia não. O som
// em si é um WAV gerado por `scripts/generate-rest-alarm.mjs` (bipes em onda
// quadrada ~2,7 kHz, < 5s para o Android não pausar a música do usuário).
//
// Celulares só deixam um <audio> tocar "sozinho" (disparado por timer, sem
// toque) depois que ESSE elemento já tocou uma vez a partir de um gesto do
// usuário. Por isso o elemento é único (singleton, sobrevive às visitas do
// Inertia) e `unlockRestAlarm()` é chamado no toque que inicia o treino ou a
// série — muito antes do alarme precisar tocar.
import { stopAudioKeepAlive, unlockAudioKeepAlive } from '@/Utils/audioAlert';

const ALARM_SRC = '/sounds/rest-alarm.wav';

// ~3,3s de vibração em três pulsos por bloco, acompanhando o "bip-bip-bip".
const VIBRATION_PATTERN = [400, 120, 400, 120, 400, 350, 400, 120, 400, 120, 400];

let alarmEl = null;
let unlocked = false;
let ringing = false;

// `navigator.audioSession` (Safari 16.4+): 'playback' faz o som ignorar a
// chave de silencioso. Só fica em 'playback' enquanto o alarme toca — nesse
// modo o iOS interrompe a música de outros apps, então voltar para 'auto'
// logo depois evita pausar o Spotify do usuário durante todo o descanso.
function setAudioSessionType(type) {
    try {
        if (navigator.audioSession) navigator.audioSession.type = type;
    } catch (_) {}
}

// Troca a URL do arquivo por um `blob:` em memória assim que ele baixa (ou
// sai do cache do Service Worker). O Safari pede mídia com cabeçalho Range e
// recusa a resposta inteira que o cache do SW devolve — com o blob, o alarme
// toca offline e nunca depende de rede no momento de tocar. A liberação do
// autoplay é do elemento, não da URL, então trocar o `src` não a perde.
function loadAlarmIntoMemory(el) {
    fetch(ALARM_SRC)
        .then((response) => (response.ok ? response.blob() : null))
        .then((blob) => {
            if (!blob || ringing) return;
            // Tipo explícito: não depende do Content-Type que o servidor mandar.
            el.src = URL.createObjectURL(new Blob([blob], { type: 'audio/wav' }));
            el.load();
        })
        .catch(() => {});
}

function getAlarmElement() {
    if (!alarmEl) {
        alarmEl = new Audio(ALARM_SRC);
        alarmEl.preload = 'auto';
        alarmEl.addEventListener('ended', () => {
            ringing = false;
            setAudioSessionType('auto');
        });
        loadAlarmIntoMemory(alarmEl);
    }
    return alarmEl;
}

// Chamar SEMPRE de dentro de um handler de toque/clique. Toca o alarme mudo
// e pausa em seguida: é o suficiente para o navegador liberar o elemento.
export function unlockRestAlarm() {
    unlockAudioKeepAlive();
    if (unlocked) return;

    const el = getAlarmElement();
    el.muted = true;

    el.play()
        .then(() => {
            unlocked = true;
            // Se o alarme de verdade começou enquanto o desbloqueio estava
            // em andamento, não interrompe.
            if (!ringing) {
                el.pause();
                el.currentTime = 0;
            }
        })
        .catch(() => {})
        .finally(() => {
            el.muted = false;
        });
}

export function playRestAlarm() {
    stopAudioKeepAlive();

    const el = getAlarmElement();
    setAudioSessionType('playback');
    ringing = true;
    el.muted = false;
    el.currentTime = 0;
    el.play().catch(() => {
        ringing = false;
        setAudioSessionType('auto');
    });

    // Android: vibra junto (no iPhone `vibrate` não existe).
    if (navigator.vibrate) navigator.vibrate(VIBRATION_PATTERN);
}

export function stopRestAlarm() {
    if (navigator.vibrate && ringing) navigator.vibrate(0);
    ringing = false;
    if (alarmEl) {
        alarmEl.pause();
        alarmEl.currentTime = 0;
    }
    setAudioSessionType('auto');
}
