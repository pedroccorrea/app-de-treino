// Gera `public/sounds/rest-alarm.wav`: o alarme de fim do descanso.
//
//   node scripts/generate-rest-alarm.mjs
//
// Sem dependências e sem baixar nada — o WAV é montado byte a byte.
//
// Desenho do som:
// - Onda quadrada a 2756,25 Hz (44100 / 16): o período cabe em exatamente
//   16 amostras (8 altas, 8 baixas), então todos os ciclos são idênticos e
//   não há "tremido" de aliasing. Fica dentro da faixa 2500–3000 Hz, onde o
//   ouvido é mais sensível e o alto-falante de celular rende mais.
// - Amplitude 0,8 do fundo de escala (~ -1,9 dBFS): a onda quadrada já é a
//   forma de onda mais "cheia" possível; a folga evita que o overshoot da
//   reconstrução no DAC (fenômeno de Gibbs, ~18%) passe de 100% e distorça.
// - Padrão "bip-bip-bip" repetido 4 vezes, total ~2,7s. Precisa ficar abaixo
//   de 5s: acima disso o Android trata o som como mídia longa e pausa a
//   música que o usuário estiver ouvindo.
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const SAMPLE_RATE = 44100;
const HALF_PERIOD_SAMPLES = 8; // 44100 / 16 = 2756,25 Hz
const AMPLITUDE = 0.8;
const BEEP_SECONDS = 0.09;
const GAP_SECONDS = 0.07;
const BEEPS_PER_GROUP = 3;
const GROUP_PAUSE_SECONDS = 0.35;
const GROUPS = 4;
// Rampa curtíssima nas bordas de cada bipe: sem ela, o corte seco gera um
// estalo audível (não muda a sensação de volume).
const RAMP_SAMPLES = 44;

const toSamples = (seconds) => Math.round(seconds * SAMPLE_RATE);

function buildSamples() {
    const samples = [];
    const peak = Math.round(AMPLITUDE * 32767);

    const pushSilence = (seconds) => {
        for (let i = 0; i < toSamples(seconds); i++) samples.push(0);
    };

    const pushBeep = () => {
        const length = toSamples(BEEP_SECONDS);
        for (let i = 0; i < length; i++) {
            const high = Math.floor(i / HALF_PERIOD_SAMPLES) % 2 === 0;
            const envelope = Math.min(1, i / RAMP_SAMPLES, (length - 1 - i) / RAMP_SAMPLES);
            samples.push(Math.round((high ? peak : -peak) * envelope));
        }
    };

    for (let group = 0; group < GROUPS; group++) {
        for (let beep = 0; beep < BEEPS_PER_GROUP; beep++) {
            pushBeep();
            if (beep < BEEPS_PER_GROUP - 1) pushSilence(GAP_SECONDS);
        }
        if (group < GROUPS - 1) pushSilence(GROUP_PAUSE_SECONDS);
    }
    pushSilence(0.05);

    return samples;
}

function encodeWav(samples) {
    const dataSize = samples.length * 2;
    const buffer = Buffer.alloc(44 + dataSize);

    buffer.write('RIFF', 0);
    buffer.writeUInt32LE(36 + dataSize, 4);
    buffer.write('WAVE', 8);
    buffer.write('fmt ', 12);
    buffer.writeUInt32LE(16, 16); // tamanho do bloco fmt
    buffer.writeUInt16LE(1, 20); // PCM
    buffer.writeUInt16LE(1, 22); // mono
    buffer.writeUInt32LE(SAMPLE_RATE, 24);
    buffer.writeUInt32LE(SAMPLE_RATE * 2, 28); // byte rate
    buffer.writeUInt16LE(2, 32); // block align
    buffer.writeUInt16LE(16, 34); // bits por amostra
    buffer.write('data', 36);
    buffer.writeUInt32LE(dataSize, 40);

    samples.forEach((sample, i) => buffer.writeInt16LE(sample, 44 + i * 2));

    return buffer;
}

const samples = buildSamples();
const outputPath = resolve(dirname(fileURLToPath(import.meta.url)), '../public/sounds/rest-alarm.wav');

mkdirSync(dirname(outputPath), { recursive: true });
writeFileSync(outputPath, encodeWav(samples));

console.log(`${outputPath} — ${(samples.length / SAMPLE_RATE).toFixed(2)}s, ${samples.length * 2 + 44} bytes`);
