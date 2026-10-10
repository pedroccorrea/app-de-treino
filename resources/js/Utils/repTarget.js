// Meta de repetições da ficha (target_reps é texto livre: "10", "8-10",
// "até a falha"...). Espelha App\DataTransferObjects\RepRange (PHP) — as duas
// pontas precisam andar juntas, porque a sessão ativa também roda offline
// (página montada pelo Service Worker a partir de fichas guardadas no
// IndexedDB, inclusive de antes de existirem target_reps_min/max).
//
// - "10", "10 cada lado"               → 10..10
// - "8-10", "8 a 10", "8–10", "10-8"   → 8..10
// - "12/10/8"                          → do menor ao maior (8..12)
// - "3x10", "4 x 8-10"                 → só o que vem depois do "x"
// - vazio, "até a falha", "AMRAP"      → null (sem meta numérica)

const MAX_PLAUSIBLE_REPS = 100;

// Sem sugestão, sem histórico e sem meta numérica: o mesmo padrão de 10
// repetições que o app sempre usou.
export const DEFAULT_REPS = 10;

export const parseRepRange = (text) => {
    if (text === null || text === undefined || String(text).trim() === '') return null;

    let value = String(text).toLowerCase();

    const setsTimesReps = value.match(/\d+\s*[x×]\s*(.+)$/u);
    if (setsTimesReps) value = setsTimesReps[1];

    const reps = (value.match(/\d+/g) ?? [])
        .map((n) => parseInt(n, 10))
        .filter((n) => n >= 1 && n <= MAX_PLAUSIBLE_REPS);

    if (reps.length === 0) return null;

    return { min: Math.min(...reps), max: Math.max(...reps) };
};

// Colunas numéricas quando o servidor já as mandou; senão interpreta o texto.
export const repRangeOf = (exercise) => {
    const min = exercise?.target_reps_min;
    const max = exercise?.target_reps_max;

    if (Number.isInteger(min) && Number.isInteger(max)) {
        return { min: Math.min(min, max), max: Math.max(min, max) };
    }

    return parseRepRange(exercise?.target_reps);
};

// Rótulo da meta onde ela já aparece: uma faixa clara vira "8–10" (com
// travessão, invertida normalizada); qualquer outro texto continua como a
// pessoa escreveu (ex.: "12/10/8", "até a falha").
export const formatRepTarget = (text) => {
    if (text === null || text === undefined) return '';

    const trimmed = String(text).trim();
    const clearRange = trimmed.match(/^(\d+)\s*(?:-|–|—|a|até)\s*(\d+)$/iu);

    if (!clearRange) return trimmed;

    const range = parseRepRange(trimmed);
    if (!range) return trimmed;

    return range.min === range.max ? String(range.min) : `${range.min}–${range.max}`;
};

const positiveInt = (value) => {
    const n = typeof value === 'number' ? value : parseInt(value, 10);
    return Number.isFinite(n) && n >= 1 ? Math.round(n) : null;
};

// REPS sugerido pela IA, sempre inteiro e dentro da faixa quando ela existe
// (o backend já garante; aqui é só a rede de segurança).
export const suggestedRepsFor = (exercise, suggestion) => {
    const reps = positiveInt(suggestion?.suggested_reps);
    if (reps === null) return null;

    const range = repRangeOf(exercise);
    if (!range || range.min === range.max) return reps;

    return Math.max(range.min, Math.min(range.max, reps));
};

// Valor inicial do REPS de uma série ainda não tocada, sempre um inteiro:
// 1) sugestão da IA; 2) repetições da última vez (lastLogs);
// 3) menor número da meta da ficha; 4) o padrão de 10.
export const initialRepsFor = (exercise, lastLog, suggestion) =>
    suggestedRepsFor(exercise, suggestion)
    ?? positiveInt(lastLog?.reps)
    ?? repRangeOf(exercise)?.min
    ?? DEFAULT_REPS;
