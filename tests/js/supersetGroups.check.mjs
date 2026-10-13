// Executado por tests/Feature/SupersetGroupFormTest.php (não há runner de
// testes JS no projeto): imprime, em JSON, o resultado do utilitário de
// exercícios combinados do frontend (`resources/js/Utils/supersetGroups.js`)
// para comparar com a regra PHP (WorkoutService::normalizeSupersetGroups) e
// verificar juntar/separar/remover/mover.
import {
    arrowTarget,
    blockArrowTarget,
    buildExerciseBlocks,
    joinWithPrevious,
    moveExercise,
    normalizeSupersetGroups,
    removeExerciseAt,
    separateFromGroup,
} from '../../resources/js/Utils/supersetGroups.js';

const normalizeCases = {
    vazio: [],
    sozinhos: [null, null, null],
    bi_set: [7, 7, null],
    grupo_de_um: [3, null, null],
    nao_vizinhos: [1, 1, null, 1, 1],
    separados_por_outro: [4, null, 4],
    vizinhos_diferentes: [2, 5, 5],
    renumera: [9, 9, 2, 2, 2],
    tri_set: [3, 3, 3],
};

// Lista A..F com grupos opcionais. Campos ausentes = ficha antiga (sem grupo).
const make = (groups) => groups.map((group, i) => ({
    id: i + 1,
    name: 'ABCDEF'[i],
    ...(group === undefined ? {} : { superset_group: group }),
}));

const view = (list) => ({
    names: list.map((e) => e.name).join(''),
    groups: list.map((e) => e.superset_group ?? null),
    orders: list.map((e) => e.order),
});

const labels = (list) => buildExerciseBlocks(list).map((b) => b.label);

console.log(JSON.stringify({
    normalize: Object.fromEntries(Object.entries(normalizeCases).map(([k, v]) => [k, normalizeSupersetGroups(v)])),

    join: {
        // A sozinho + B sozinho → bi-set novo.
        cria_bi_set: view(joinWithPrevious(make([null, null, null]), 1)),
        // C entra no bi-set A+B → tri-set.
        vira_tri_set: view(joinWithPrevious(make([1, 1, null]), 2)),
        // Bi-set C+D juntado com A sozinho acima? Não: A está longe. B sozinho acima de C+D.
        bloco_entra_no_de_cima: view(joinWithPrevious(make([null, null, 1, 1]), 2)),
        // Primeiro exercício não tem "de cima".
        primeiro_nao_muda: view(joinWithPrevious(make([null, null]), 0)),
        // Ficha antiga sem o campo.
        ficha_antiga: view(joinWithPrevious(make([undefined, undefined, undefined]), 2)),
        rotulo_quatro: labels(joinWithPrevious(make([1, 1, 1, null]), 3)),
    },

    separate: {
        ponta_do_tri_set: view(separateFromGroup(make([1, 1, 1]), 2)),
        meio_do_tri_set: view(separateFromGroup(make([1, 1, 1]), 1)),
        bi_set_desfaz: view(separateFromGroup(make([1, 1, null]), 0)),
        segundo_de_quatro: view(separateFromGroup(make([1, 1, 1, 1]), 1)),
    },

    remove: {
        meio_do_tri_set: view(removeExerciseAt(make([1, 1, 1]), 1)),
        um_do_bi_set: view(removeExerciseAt(make([1, 1, null]), 0)),
    },

    move: {
        // Arrastar D (sozinho) para cima de B (meio do tri-set A,B,C): vai para antes do bloco.
        sozinho_sobe_no_grupo: view(moveExercise(make([1, 1, 1, null]), 3, 1)),
        // Arrastar A (sozinho) para baixo sobre C (meio do tri-set B,C,D): vai para depois do bloco.
        sozinho_desce_no_grupo: view(moveExercise(make([null, 1, 1, 1]), 0, 2)),
        // Dentro do grupo: só troca a ordem interna.
        dentro_do_grupo: view(moveExercise(make([1, 1, 1, null]), 0, 2)),
        // Exercício do grupo arrastado para fora: o bloco inteiro vai junto.
        grupo_sai_inteiro: view(moveExercise(make([null, null, 1, 1]), 3, 0)),
        // Ficha sem grupo continua igual a antes (move um exercício só).
        sem_grupo: view(moveExercise(make([null, null, null, null]), 0, 2)),
    },

    arrows: {
        // Setas do sozinho pulam o bloco vizinho inteiro.
        sozinho_sobe: arrowTarget(make([1, 1, null]), 2, -1),
        sozinho_sobe_resultado: view(moveExercise(make([1, 1, null]), 2, arrowTarget(make([1, 1, null]), 2, -1))),
        // Dentro do grupo: só até as pontas do grupo.
        grupo_ponta_cima: arrowTarget(make([null, 1, 1]), 1, -1),
        grupo_interno: arrowTarget(make([null, 1, 1]), 1, 1),
        // Setas do cabeçalho do bloco movem o grupo inteiro.
        bloco_sobe: (() => {
            const list = make([null, 1, 1]);
            const t = blockArrowTarget(list, 1, -1);
            return view(moveExercise(list, t.from, t.to));
        })(),
        bloco_primeiro: blockArrowTarget(make([1, 1, null]), 0, -1),
    },
}));
