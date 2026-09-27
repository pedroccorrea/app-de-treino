// Passo dos botões + e − de carga na sessão ativa, conforme o equipamento do
// exercício. Espelha ValidLoadService::stepFrom() (PHP) — as duas pontas
// precisam andar juntas, porque a sessão ativa também roda offline (página
// montada pelo Service Worker, sem servidor para calcular o passo).
//
// Tudo é feito em centésimos de kg (inteiros) para 40 + 2,5 nunca virar
// 42,499999.

export const DEFAULT_LOAD_INCREMENT = 2.5;

const KETTLEBELLS = [4, 6, 8, 10, 12, 14, 16, 20, 24, 28, 32].map((kg) => kg * 100);

const toCents = (kg) => Math.round(kg * 100);
const fromCents = (cents) => cents / 100;

// Barra/máquina com anilhas: o menor aumento é um par de 2,5 kg, contado a
// partir da carga atual (+5 kg no total, que é como o app registra). Peso
// corporal: uma anilha de 2,5 kg extra.
const relativeStep = (equipment) => {
    if (equipment === 'barra_anilhas' || equipment === 'maquina_anilhas') return 500;
    if (equipment === 'peso_corporal') return 250;
    return null;
};

const nextAboveCents = (equipment, cents) => {
    const step = relativeStep(equipment);
    if (step !== null) return cents + step;

    switch (equipment) {
        case 'halteres':
            return cents < 1000
                ? Math.max(100, (Math.floor(cents / 100) + 1) * 100)
                : (Math.floor(cents / 200) + 1) * 200;
        case 'maquina_placas':
            return Math.max(500, (Math.floor(cents / 500) + 1) * 500);
        case 'kettlebell':
            return KETTLEBELLS.find((kg) => kg > cents) ?? null;
        default:
            return null;
    }
};

const nextBelowCents = (equipment, cents) => {
    const step = relativeStep(equipment);
    if (step !== null) return cents - step >= 0 ? cents - step : null;

    switch (equipment) {
        case 'halteres': {
            const below = cents > 1000
                ? (Math.ceil(cents / 200) - 1) * 200
                : (Math.ceil(cents / 100) - 1) * 100;
            return below >= 100 ? below : null;
        }
        case 'maquina_placas': {
            const below = (Math.ceil(cents / 500) - 1) * 500;
            return below >= 500 ? below : null;
        }
        case 'kettlebell':
            return [...KETTLEBELLS].reverse().find((kg) => kg < cents) ?? null;
        default:
            return null;
    }
};

const isRestricted = (equipment) => Boolean(equipment) && equipment !== 'outro';

/**
 * Próxima carga ao tocar + (direction > 0) ou − (direction < 0).
 * Um incremento configurado pelo usuário tem prioridade; sem ele, o passo
 * segue o equipamento. Sem equipamento (ainda não classificado) ou "outro":
 * passo padrão de 2,5 kg, como sempre foi.
 */
export function stepLoad(equipment, current, direction, customIncrement = null) {
    const currentCents = toCents(Number(current) || 0);

    if ((customIncrement !== null && customIncrement > 0) || !isRestricted(equipment)) {
        const increment = toCents(customIncrement > 0 ? customIncrement : DEFAULT_LOAD_INCREMENT);
        return fromCents(Math.max(0, currentCents + Math.sign(direction) * increment));
    }

    const next = direction > 0
        ? nextAboveCents(equipment, currentCents)
        : nextBelowCents(equipment, currentCents);

    return fromCents(next ?? currentCents);
}
