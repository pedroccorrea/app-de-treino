// Executado por tests/Feature/SupersetRoundsTest.php (não há runner de testes
// JS no projeto): imprime, em JSON, o resultado do utilitário de rodadas do
// treino em andamento (`resources/js/Utils/supersetRounds.js`) para cenários
// fixos de bi-set/tri-set.
import {
    buildSessionBlocks,
    currentRound,
    extraRoundExercises,
    roundExercises,
    roundRestSeconds,
    roundTitle,
    totalRounds,
} from '../../resources/js/Utils/supersetRounds.js';

// Lista A..F: [superset_group, target_sets, rest_seconds?].
const make = (specs) => specs.map(([group, sets, rest], i) => ({
    id: i + 1,
    name: 'ABCDEF'[i],
    superset_group: group,
    target_sets: sets,
    ...(rest === undefined ? {} : { rest_seconds: rest }),
}));

// Estado do treino em dados puros: séries feitas, extras e encerrados por nome.
const statusOf = ({ done = {}, extra = {}, finished = [] } = {}) => ({
    totalSets: (e) => (e.target_sets ?? 3) + (extra[e.name] ?? 0),
    isFinished: (e) => finished.includes(e.name),
    isSetDone: (e, n) => (done[e.name] ?? []).includes(n),
});

const names = (list) => list.map((e) => e.name).join('');

// Rodadas 1..total do bloco, cada uma com os nomes dos exercícios que a compõem.
const rounds = (exercises, status) => {
    const total = totalRounds(exercises, status);
    return Array.from({ length: total }, (_, i) => names(roundExercises(exercises, i + 1, status)));
};

const blocksView = (list) => buildSessionBlocks(list).map((b) => ({
    names: names(b.exercises),
    isGroup: b.isGroup,
    label: b.label,
    indexes: b.indexes,
}));

const biSet33 = make([[1, 3], [1, 3]]);
const biSet43 = make([[1, 4], [1, 3]]);
const triSet = make([[1, 3, 45], [1, 3, 60], [1, 3, 90]]);

console.log(JSON.stringify({
    blocks: {
        sem_grupo: blocksView(make([[null, 3], [null, 3]])),
        bi_set_e_sozinho: blocksView(make([[1, 3], [1, 3], [null, 3]])),
        grupo_de_um: blocksView(make([[5, 3], [null, 3]])),
        nao_vizinhos: blocksView(make([[2, 3], [null, 3], [2, 3]])),
        tri_set: blocksView(triSet),
    },

    bi_set_3x3: {
        total: totalRounds(biSet33, statusOf()),
        rounds: rounds(biSet33, statusOf()),
        current_inicio: currentRound(biSet33, statusOf()),
        current_apos_rodada_1: currentRound(biSet33, statusOf({ done: { A: [1], B: [1] } })),
        // Rodada 1 só pela metade (a do B falhou online): continua na rodada 1.
        current_rodada_incompleta: currentRound(biSet33, statusOf({ done: { A: [1] } })),
        current_fim: currentRound(biSet33, statusOf({ done: { A: [1, 2, 3], B: [1, 2, 3] } })),
        title: roundTitle(buildSessionBlocks(biSet33)[0].label, 1, 3),
    },

    bi_set_4x3: {
        total: totalRounds(biSet43, statusOf()),
        rounds: rounds(biSet43, statusOf()),
        current_rodada_4: currentRound(biSet43, statusOf({ done: { A: [1, 2, 3], B: [1, 2, 3] } })),
    },

    tri_set: {
        label: buildSessionBlocks(triSet)[0].label,
        rounds: rounds(triSet, statusOf()),
        rest: roundRestSeconds(roundExercises(triSet, 1, statusOf())),
        // C encerrado: o descanso passa a ser o do último da rodada (B).
        rest_sem_c: roundRestSeconds(roundExercises(triSet, 2, statusOf({ finished: ['C'] }))),
        rest_padrao: roundRestSeconds(make([[1, 3], [1, 3]])),
    },

    finalizar_exercicio: {
        // B encerrado depois da rodada 1: as próximas rodadas só com A.
        rounds: rounds(biSet33, statusOf({ done: { A: [1], B: [1] }, finished: ['B'] })),
        current: currentRound(biSet33, statusOf({ done: { A: [1], B: [1] }, finished: ['B'] })),
        // Todos encerrados: o grupo terminou.
        todos: currentRound(biSet33, statusOf({ done: { A: [1], B: [1] }, finished: ['A', 'B'] })),
    },

    series_extras: {
        // Uma série extra no B de um 3×3: 4 rodadas, a 4ª só com B.
        total: totalRounds(biSet33, statusOf({ extra: { B: 1 } })),
        rounds: rounds(biSet33, statusOf({ extra: { B: 1 } })),
        current: currentRound(biSet33, statusOf({ done: { A: [1, 2, 3], B: [1, 2, 3] }, extra: { B: 1 } })),
        rodada_extra: names(extraRoundExercises(biSet43, statusOf())),
        rodada_extra_3x3: names(extraRoundExercises(biSet33, statusOf())),
        rodada_extra_um_encerrado: names(extraRoundExercises(biSet33, statusOf({ finished: ['A'] }))),
        rodada_extra_todos_encerrados: names(extraRoundExercises(biSet33, statusOf({ finished: ['A', 'B'] }))),
    },
}));
