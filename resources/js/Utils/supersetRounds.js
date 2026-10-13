/**
 * Rodadas de um bi-set/tri-set no treino em andamento (Phase 53).
 *
 * No treino, um grupo combinado vira UM card por rodada: a rodada N reúne a
 * série N de cada exercício do grupo, feitas em sequência e registradas num
 * toque só ("Concluir rodada"). Cada série continua sendo um SetLog normal,
 * contado por exercício — "rodada" é só a forma de agrupar na tela.
 *
 * Regras:
 * - Blocos: exercício sozinho ou grupo de VIZINHOS com o mesmo
 *   `superset_group` (mesma limpeza da ficha, `normalizeSupersetGroups`):
 *   grupo inválido no cliente vira exercícios sozinhos.
 * - Total de rodadas = maior número de séries entre os exercícios do grupo
 *   (planejadas + extras).
 * - A rodada N inclui só os exercícios que têm a série N e que não foram
 *   encerrados pelo "Finalizar exercício".
 * - Rodada atual = a primeira rodada que ainda tem algum exercício com a
 *   série N pendente; `null` quando o grupo terminou.
 *
 * Nada aqui conhece o Vue: o estado do treino entra por um objeto `status`
 * com três funções, e o resultado é sempre derivado (nada é mutado).
 * Verificado por `tests/js/supersetRounds.check.mjs`
 * (`tests/Feature/SupersetRoundsTest.php`).
 *
 * @typedef {object} RoundStatus
 * @property {(exercise: object) => number} totalSets         Séries planejadas + extras do exercício
 * @property {(exercise: object) => boolean} isFinished       Encerrado pelo "Finalizar exercício"
 * @property {(exercise: object, setNumber: number) => boolean} isSetDone  Série já registrada
 */
import { buildExerciseBlocks } from './supersetGroups.js';

/** Descanso usado quando o exercício não define o seu (o mesmo de hoje). */
export const DEFAULT_REST_SECONDS = 60;

/**
 * Divide a lista ordenada de exercícios em blocos do treino.
 *
 * @returns {Array<{ key: string, isGroup: boolean, label: string|null, exercises: object[], indexes: number[] }>}
 */
export function buildSessionBlocks(exercises) {
    return buildExerciseBlocks(exercises).map((block) => ({
        key: block.key,
        isGroup: block.items.length > 1,
        label: block.items.length > 1 ? block.label : null,
        exercises: block.items.map((item) => item.exercise),
        indexes: block.items.map((item) => item.index),
    }));
}

/** Bloco que contém o exercício da posição `index` (ou `null`). */
export function blockForIndex(blocks, index) {
    return blocks.find((block) => block.indexes.includes(index)) ?? null;
}

/** Maior número de séries entre os exercícios do grupo. */
export function totalRounds(exercises, status) {
    return exercises.reduce((max, exercise) => Math.max(max, status.totalSets(exercise)), 0);
}

/** Exercícios que fazem parte da rodada `round` (têm a série e não foram encerrados). */
export function roundExercises(exercises, round, status) {
    return exercises.filter((exercise) => status.totalSets(exercise) >= round && !status.isFinished(exercise));
}

/** Exercícios da rodada cuja série `round` ainda não foi registrada. */
export function pendingRoundExercises(exercises, round, status) {
    return roundExercises(exercises, round, status).filter((exercise) => !status.isSetDone(exercise, round));
}

/** Primeira rodada com alguma série pendente, ou `null` quando o grupo terminou. */
export function currentRound(exercises, status) {
    const total = totalRounds(exercises, status);

    for (let round = 1; round <= total; round++) {
        if (pendingRoundExercises(exercises, round, status).length > 0) return round;
    }

    return null;
}

/** Ex.: "Bi-set · Rodada 1 de 3". */
export function roundTitle(label, round, total) {
    return `${label ?? 'Combinado'} · Rodada ${round} de ${total}`;
}

/**
 * Descanso depois de concluir a rodada: o do ÚLTIMO exercício da rodada (na
 * ordem da ficha) — é ele que acabou de ser feito quando o descanso começa.
 * Sem valor na ficha, o mesmo padrão de hoje (60 s).
 */
export function roundRestSeconds(exercisesInRound) {
    const last = exercisesInRound[exercisesInRound.length - 1];

    return last?.rest_seconds ?? DEFAULT_REST_SECONDS;
}

/**
 * Quem ganha uma série a mais em "+ Adicionar rodada extra" (grupo já
 * terminado): os exercícios que chegaram até a última rodada e não foram
 * encerrados antes — assim a nova série de cada um é exatamente a próxima
 * rodada. Se todos foram encerrados, os que chegaram à última rodada voltam.
 */
export function extraRoundExercises(exercises, status) {
    const total = totalRounds(exercises, status);
    const reachedLast = exercises.filter((exercise) => status.totalSets(exercise) === total);
    const active = reachedLast.filter((exercise) => !status.isFinished(exercise));

    return active.length > 0 ? active : reachedLast;
}

/**
 * Progresso de um bloco pelas rodadas (Phase 54) — usado no stepper e no
 * menu lateral: `current` é a rodada atual (`null` quando o grupo terminou)
 * e `done` quantas rodadas já foram concluídas.
 *
 * @returns {{ current: number|null, total: number, done: number }}
 */
export function roundProgress(exercises, status) {
    const total = totalRounds(exercises, status);
    const current = currentRound(exercises, status);

    return { current, total, done: current === null ? total : current - 1 };
}

/**
 * Onde reabrir o treino em andamento (Phase 54) — vale para TODOS os
 * treinos, com ou sem grupo. Calculado só pelas séries já feitas (e pelos
 * exercícios encerrados com "Finalizar exercício"):
 * - o primeiro bloco, na ordem da ficha, que ainda tem série pendente;
 * - dentro de um grupo, a rodada atual (`currentRound`);
 * - num exercício sozinho, a primeira série ainda não feita;
 * - tudo feito: o último bloco, como fica hoje ao terminar o treino.
 *
 * `index` é o índice (na lista ordenada) do primeiro exercício do bloco —
 * o mesmo `currentIndex` que a navegação usa.
 *
 * @returns {{ index: number, blockIndex: number, round: number|null, setNumber: number|null }}
 */
export function resumePosition(exercises, status) {
    const blocks = buildSessionBlocks(exercises);

    for (let blockIndex = 0; blockIndex < blocks.length; blockIndex++) {
        const block = blocks[blockIndex];

        if (block.isGroup) {
            const round = currentRound(block.exercises, status);
            if (round !== null) return { index: block.indexes[0], blockIndex, round, setNumber: null };
            continue;
        }

        const exercise = block.exercises[0];
        if (status.isFinished(exercise)) continue;

        const total = status.totalSets(exercise);
        for (let n = 1; n <= total; n++) {
            if (!status.isSetDone(exercise, n)) return { index: block.indexes[0], blockIndex, round: null, setNumber: n };
        }
    }

    const last = blocks.length - 1;

    return { index: last >= 0 ? blocks[last].indexes[0] : 0, blockIndex: Math.max(0, last), round: null, setNumber: null };
}
