// Executado por tests/Feature/RestAlarmAppleDeviceTest.php (não há runner de
// testes JS no projeto). Phase 50: no iPhone/iPad o app não toca nenhum
// áudio; no Android o alarme continua exatamente como antes.
//
// Uso: node tests/js/restAlarm.check.mjs <cenário>
//   detect           → resultado da detecção para user agents reais
//   iphone | ipad-mac | android → simula o aparelho (navigator e Audio
//                      falsos), roda o fluxo do treino e imprime o que os
//                      utilitários de áudio fizeram.
// Cada cenário roda num processo próprio: os utilitários guardam estado de
// módulo (elementos singleton, "já desbloqueado"), como no navegador.

const scenario = process.argv[2] ?? 'detect';

const UA = {
    iphone_safari:
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
    iphone_chrome:
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/126.0.6478.54 Mobile/15E148 Safari/604.1',
    iphone_firefox:
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) FxiOS/127.0 Mobile/15E148 Safari/605.1.15',
    iphone_edge:
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 EdgiOS/126.2592.56 Mobile/15E148 Safari/605.1.15',
    // App instalado na tela de início: sem "Safari/" no user agent.
    iphone_standalone:
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148',
    ipad_legacy:
        'Mozilla/5.0 (iPad; CPU OS 12_5_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1.2 Mobile/15E148 Safari/604.1',
    // iPadOS 13+ (Safari em modo "site para computador"): igual a um Mac.
    mac_safari:
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15',
    mac_chrome:
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
    android_phone:
        'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.6478.71 Mobile Safari/537.36',
    android_tablet:
        'Mozilla/5.0 (Linux; Android 14; SM-X710) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
    windows_chrome:
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
};

const DEVICES = {
    iphone: { userAgent: UA.iphone_safari, platform: 'iPhone', maxTouchPoints: 5 },
    'ipad-mac': { userAgent: UA.mac_safari, platform: 'MacIntel', maxTouchPoints: 5 },
    android: { userAgent: UA.android_phone, platform: 'Linux armv81', maxTouchPoints: 5 },
};

function setGlobal(name, value) {
    Object.defineProperty(globalThis, name, { value, configurable: true, writable: true });
}

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

async function detect() {
    const { isAppleMobileDevice } = await import('../../resources/js/Utils/appleMobileDevice.js');

    const cases = {
        iphone_safari: { userAgent: UA.iphone_safari, platform: 'iPhone', maxTouchPoints: 5 },
        iphone_chrome: { userAgent: UA.iphone_chrome, platform: 'iPhone', maxTouchPoints: 5 },
        iphone_firefox: { userAgent: UA.iphone_firefox, platform: 'iPhone', maxTouchPoints: 5 },
        iphone_edge: { userAgent: UA.iphone_edge, platform: 'iPhone', maxTouchPoints: 5 },
        iphone_standalone: { userAgent: UA.iphone_standalone, platform: 'iPhone', maxTouchPoints: 5 },
        iphone_ua_only: { userAgent: UA.iphone_safari },
        ipad_legacy: { userAgent: UA.ipad_legacy, platform: 'iPad', maxTouchPoints: 5 },
        ipad_as_mac: { userAgent: UA.mac_safari, platform: 'MacIntel', maxTouchPoints: 5 },
        ipad_as_mac_ua_only: { userAgent: UA.mac_safari, maxTouchPoints: 5 },
        android_phone: { userAgent: UA.android_phone, platform: 'Linux armv81', maxTouchPoints: 5 },
        android_tablet: { userAgent: UA.android_tablet, platform: 'Android', maxTouchPoints: 10 },
        mac_safari_no_touch: { userAgent: UA.mac_safari, platform: 'MacIntel', maxTouchPoints: 0 },
        mac_chrome_no_touch: { userAgent: UA.mac_chrome, platform: 'macOS', maxTouchPoints: 0 },
        windows_desktop: { userAgent: UA.windows_chrome, platform: 'Windows', maxTouchPoints: 0 },
        windows_touchscreen: { userAgent: UA.windows_chrome, platform: 'Win32', maxTouchPoints: 10 },
    };

    const result = Object.fromEntries(
        Object.entries(cases).map(([name, device]) => [name, isAppleMobileDevice(device)]),
    );

    // Sem parâmetros: lê do `navigator` do navegador (aqui, um falso).
    setGlobal('navigator', { userAgent: UA.mac_safari, platform: 'MacIntel', maxTouchPoints: 5 });
    result.from_navigator_ipad = isAppleMobileDevice();
    setGlobal('navigator', {
        userAgent: UA.mac_chrome,
        platform: 'MacIntel',
        userAgentData: { platform: 'macOS' },
        maxTouchPoints: 0,
    });
    result.from_navigator_mac = isAppleMobileDevice();

    // Fora do navegador (sem `navigator`): não quebra.
    setGlobal('navigator', undefined);
    result.without_navigator = isAppleMobileDevice();

    return result;
}

async function simulate(device) {
    const log = { audio_created: [], play: [], pause: 0, audio_session_writes: [], media_session: [], vibrate: [] };

    class FakeAudio {
        constructor(src) {
            this.src = src;
            this.paused = true;
            this.muted = false;
            this.currentTime = 0;
            this.loop = false;
            this.volume = 1;
            log.audio_created.push(src.startsWith('data:') ? 'silence' : src);
        }

        play() {
            log.play.push({
                src: this.src.startsWith('data:') ? 'silence' : this.src,
                muted: this.muted,
                loop: this.loop,
                volume: this.volume,
            });
            this.paused = false;
            return Promise.resolve();
        }

        pause() {
            log.pause++;
            this.paused = true;
        }

        load() {}

        addEventListener() {}
    }

    let audioSessionType = 'auto';
    const audioSession = {
        get type() {
            return audioSessionType;
        },
        set type(value) {
            log.audio_session_writes.push(value);
            audioSessionType = value;
        },
    };

    let metadata = null;
    const mediaSession = {
        get metadata() {
            return metadata;
        },
        set metadata(value) {
            log.media_session.push({ metadata: { ...value } });
            metadata = value;
        },
        set playbackState(value) {
            log.media_session.push({ playbackState: value });
        },
    };

    const nav = { ...DEVICES[device], audioSession, mediaSession };
    // O iPhone não tem `navigator.vibrate`; o Android tem.
    if (device === 'android') nav.vibrate = (pattern) => log.vibrate.push(pattern);

    setGlobal('navigator', nav);
    setGlobal('Audio', FakeAudio);
    setGlobal('MediaMetadata', class {
        constructor(data) {
            Object.assign(this, data);
        }
    });
    // O download do WAV para blob não faz parte do que é verificado aqui.
    setGlobal('fetch', () => Promise.resolve({ ok: false }));

    const { playRestAlarm, stopRestAlarm, unlockRestAlarm } = await import('../../resources/js/Utils/restAlarm.js');
    const { startAudioKeepAlive, stopAudioKeepAlive, unlockAudioKeepAlive } = await import(
        '../../resources/js/Utils/audioAlert.js'
    );

    const steps = {};
    const snapshot = (name) => {
        steps[name] = JSON.parse(JSON.stringify(log));
    };

    // "Iniciar treino" (ficha ou dashboard).
    unlockRestAlarm();
    await flush();
    snapshot('start_workout_unlock');

    // Primeiro toque na tela do treino e "Concluir série".
    unlockRestAlarm();
    await flush();
    unlockRestAlarm();
    await flush();
    snapshot('later_unlocks');

    unlockAudioKeepAlive();
    await flush();

    // Começa o descanso.
    startAudioKeepAlive();
    await flush();
    snapshot('rest_started');

    // Fim do descanso.
    playRestAlarm();
    await flush();
    steps.audio_session_while_ringing = audioSessionType;
    snapshot('alarm_played');

    // "Encerrar descanso" / saída da página.
    stopRestAlarm();
    stopAudioKeepAlive();
    await flush();
    steps.audio_session_after_stop = audioSessionType;
    snapshot('stopped');

    return steps;
}

const output = scenario === 'detect' ? await detect() : await simulate(scenario);

console.log(JSON.stringify(output));
