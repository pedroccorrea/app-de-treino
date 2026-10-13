// Executado por tests/Feature/SessionResumePositionTest.php (não há runner de
// testes JS no projeto): imprime, em JSON, onde o treino em andamento reabre
// (`resumePosition` em `resources/js/Utils/supersetRounds.js`, Phase 54) e o
// progresso por rodadas usado no stepper/menu lateral (`roundProgress`).
import { buildSessionBlocks, resumePosition, roundProgress } from '../../resources/js/Utils/supersetRounds.js';

// Lista A..F: [superset_group, target_sets].
const make = (specs) => specs.map(([group, sets], i) => ({
    id: i + 1,
    name: 'ABCDEF'[i],
    superset_group: group,
    target_sets: sets,
}));

// Estado do treino em dados puros: séries feitas, extras e encerrados por nome.
const statusOf = ({ done = {}, extra = {}, finished = [] } = {}) => ({
    totalSets: (e) => (e.target_sets ?? 3) + (extra[e.name] ?? 0),
    isFinished: (e) => finished.includes(e.name),
    isSetDone: (e, n) => (done[e.name] ?? []).includes(n),
});

// Posição + nome do exercício onde o treino reabre.
const resume = (list, state) => {
    const position = resumePosition(list, statusOf(state));
    return { ...position, name: list[position.index]?.name ?? null };
};

const semGrupo = make([[null, 3], [null, 3], [null, 3]]);
const biSetESozinho = make([[1, 3], [1, 3], [null, 3]]);
const sozinhoEBiSet = make([[null, 3], [1, 3], [1, 3]]);
// Ficha antiga (IndexedDB anterior à Phase 52): exercícios sem o campo.
const fichaAntiga = semGrupo.map(({ superset_group, ...exercise }) => exercise);

console.log(JSON.stringify({
    sem_grupo: {
        inicio: resume(semGrupo, {}),
        meio_do_segundo: resume(semGrupo, { done: { A: [1, 2, 3], B: [1] } }),
        // Série do meio pulada: volta para a primeira que falta.
        serie_pulada: resume(semGrupo, { done: { A: [1, 3] } }),
        primeiro_finalizado: resume(semGrupo, { done: { A: [1] }, finished: ['A'] }),
        tudo_feito: resume(semGrupo, { done: { A: [1, 2, 3], B: [1, 2, 3], C: [1, 2, 3] } }),
        tudo_feito_com_finalizado: resume(semGrupo, { done: { A: [1, 2, 3], B: [1], C: [1, 2, 3] }, finished: ['B'] }),
        com_serie_extra: resume(semGrupo, { done: { A: [1, 2, 3] }, extra: { A: 1 } }),
        ficha_antiga: resume(fichaAntiga, { done: { A: [1, 2, 3], B: [1, 2] } }),
    },

    bi_set: {
        inicio: resume(biSetESozinho, {}),
        rodada_1_feita: resume(biSetESozinho, { done: { A: [1], B: [1] } }),
        // Rodada pela metade (a série do B não salvou): continua na rodada 1.
        rodada_incompleta: resume(biSetESozinho, { done: { A: [1] } }),
        // B encerrado depois da rodada 1: segue na rodada 2 só com o A.
        um_finalizado: resume(biSetESozinho, { done: { A: [1], B: [1] }, finished: ['B'] }),
        grupo_feito: resume(biSetESozinho, { done: { A: [1, 2, 3], B: [1, 2, 3], C: [1] } }),
        grupo_todo_finalizado: resume(biSetESozinho, { done: { A: [1], B: [1] }, finished: ['A', 'B'] }),
        tudo_feito: resume(biSetESozinho, { done: { A: [1, 2, 3], B: [1, 2, 3], C: [1, 2, 3] } }),
        // Último bloco é o grupo: reabre no primeiro exercício dele.
        tudo_feito_grupo_no_fim: resume(sozinhoEBiSet, { done: { A: [1, 2, 3], B: [1, 2, 3], C: [1, 2, 3] } }),
        sozinho_feito_grupo_no_fim: resume(sozinhoEBiSet, { done: { A: [1, 2, 3], B: [1, 2], C: [1, 2] } }),
    },

    vazio: resumePosition([], statusOf()),

    progresso: {
        inicio: roundProgress(buildSessionBlocks(biSetESozinho)[0].exercises, statusOf()),
        rodada_2: roundProgress(buildSessionBlocks(biSetESozinho)[0].exercises, statusOf({ done: { A: [1], B: [1] } })),
        fim: roundProgress(buildSessionBlocks(biSetESozinho)[0].exercises, statusOf({ done: { A: [1, 2, 3], B: [1, 2, 3] } })),
    },
}));
