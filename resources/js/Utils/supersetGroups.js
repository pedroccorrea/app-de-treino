/**
 * Exercícios combinados (bi-set/tri-set) na ficha — regra do frontend.
 *
 * `superset_group` é só um número que diz "quem está junto": exercícios
 * VIZINHOS com o mesmo número formam um bloco. A regra de limpeza é o espelho
 * exato de `WorkoutService::normalizeSupersetGroups()` (PHP); o backend
 * continua sendo a garantia final, mas aplicar a mesma regra aqui faz a tela
 * mostrar exatamente o que vai ser gravado.
 *
 * Comportamento de mover (setas e arrastar), escolhido para que nenhum grupo
 * seja quebrado sem querer:
 * - Um exercício sozinho anda por cima do bloco vizinho INTEIRO: nunca cai no
 *   meio de um grupo.
 * - Um exercício dentro de um grupo só troca de lugar com os do próprio grupo
 *   (ordem interna). Para levar o grupo para outro lugar, move-se o bloco
 *   (setas do cabeçalho do bloco, ou arrastando um exercício do grupo para
 *   fora dele — o bloco inteiro vai junto).
 * - Para tirar um exercício do grupo, só o botão "Separar".
 *
 * Arquivo sem dependências do Vue, verificado contra a regra PHP por
 * `tests/js/supersetGroups.check.mjs` (`tests/Feature/SupersetGroupFormTest.php`).
 */

/**
 * Lê o grupo de um exercício; fichas antigas (servidor ou IndexedDB) sem o
 * campo contam como sem grupo.
 */
export function groupOf(exercise) {
    const group = exercise?.superset_group;

    if (group === null || group === undefined || group === '') return null;

    const number = Number(group);

    return Number.isFinite(number) ? number : null;
}

/**
 * Espelho de `WorkoutService::normalizeSupersetGroups()`: recebe os grupos na
 * ordem final e devolve o grupo limpo de cada posição — trechos contínuos de
 * 2+ vizinhos com o mesmo número viram um grupo, renumerados 1, 2, 3...; o
 * resto volta a ser sozinho (null).
 *
 * @param {Array<number|null>} groups
 * @returns {Array<number|null>}
 */
export function normalizeSupersetGroups(groups) {
    const normalized = groups.map(() => null);
    let nextGroup = 1;
    let runStart = 0;

    for (let i = 1; i <= groups.length; i++) {
        const runContinues = i < groups.length
            && groups[i] !== null
            && groups[i] === groups[runStart];

        if (runContinues) continue;

        if (groups[runStart] !== null && groups[runStart] !== undefined && i - runStart >= 2) {
            for (let j = runStart; j < i; j++) {
                normalized[j] = nextGroup;
            }

            nextGroup++;
        }

        runStart = i;
    }

    return normalized;
}

/** Rótulo do bloco pelo tamanho do grupo. */
export function supersetLabel(size) {
    if (size === 2) return 'Bi-set';
    if (size === 3) return 'Tri-set';

    return 'Combinado';
}

/**
 * Aplica a regra sobre a lista (na ordem atual): grava em cada exercício o
 * `order` da posição e o `superset_group` normalizado. Muta os objetos (os
 * mesmos que os inputs do formulário editam) e devolve a própria lista.
 */
export function normalizeExerciseList(exercises) {
    const normalized = normalizeSupersetGroups(exercises.map(groupOf));

    exercises.forEach((exercise, index) => {
        exercise.order = index;
        exercise.superset_group = normalized[index];
    });

    return exercises;
}

/**
 * Agrupa a lista em blocos para exibição: cada exercício sozinho é um bloco
 * de 1; cada grupo é um bloco com rótulo. Não muta nada.
 *
 * @returns {Array<{ key: string, group: number|null, label: string|null, items: Array<{ exercise: object, index: number }> }>}
 */
export function buildExerciseBlocks(exercises) {
    const normalized = normalizeSupersetGroups(exercises.map(groupOf));
    const blocks = [];

    exercises.forEach((exercise, index) => {
        const group = normalized[index];
        const last = blocks[blocks.length - 1];

        if (group !== null && last && last.group === group) {
            last.items.push({ exercise, index });
        } else {
            blocks.push({ group, items: [{ exercise, index }] });
        }
    });

    return blocks.map((block) => ({
        ...block,
        key: `block-${block.items[0].exercise.id}`,
        label: block.group !== null ? supersetLabel(block.items.length) : null,
    }));
}

function blockIndexOf(blocks, index) {
    return blocks.findIndex((block) => block.items.some((item) => item.index === index));
}

function nextFreeGroup(exercises) {
    return Math.max(0, ...exercises.map(groupOf).filter((group) => group !== null)) + 1;
}

/**
 * "Juntar com o de cima": o bloco que começa em `index` (um exercício
 * sozinho, ou um grupo inteiro) entra no grupo do bloco logo acima. Se o de
 * cima não tem grupo, nasce um grupo novo com os dois. Sozinho + bi-set =
 * tri-set, e assim por diante.
 */
export function joinWithPrevious(exercises, index) {
    const list = normalizeExerciseList([...exercises]);
    const blocks = buildExerciseBlocks(list);
    const blockIndex = blockIndexOf(blocks, index);

    // Primeiro bloco, ou exercício que já está junto com o de cima.
    if (blockIndex <= 0 || blocks[blockIndex].items[0].index !== index) return list;

    const previous = blocks[blockIndex - 1];
    const group = previous.group ?? nextFreeGroup(list);

    [...previous.items, ...blocks[blockIndex].items].forEach(({ exercise }) => {
        exercise.superset_group = group;
    });

    return normalizeExerciseList(list);
}

/**
 * "Separar": tira o exercício do grupo. Mesma regra do backend: quem ficar
 * sozinho volta a ser exercício sozinho, e separar o do meio de um tri-set
 * desfaz o grupo inteiro (as pontas deixam de ser vizinhas).
 */
export function separateFromGroup(exercises, index) {
    const list = normalizeExerciseList([...exercises]);

    if (list[index]) list[index].superset_group = null;

    return normalizeExerciseList(list);
}

/** Remove o exercício da posição e reaplica a regra no que sobrou. */
export function removeExerciseAt(exercises, index) {
    const list = [...exercises];
    list.splice(index, 1);

    return normalizeExerciseList(list);
}

/**
 * Move do índice `from` para o índice `to` (arrastar e soltar), sem nunca
 * quebrar um grupo:
 * - Os dois no mesmo grupo: troca só a ordem interna do grupo.
 * - Blocos diferentes: o bloco inteiro de `from` (sozinho ou grupo) vai para
 *   antes do bloco de `to` (se subiu) ou depois dele (se desceu).
 */
export function moveExercise(exercises, from, to) {
    const list = normalizeExerciseList([...exercises]);
    if (from === to || !list[from] || !list[to]) return list;

    const blocks = buildExerciseBlocks(list);
    const fromBlock = blockIndexOf(blocks, from);
    const toBlock = blockIndexOf(blocks, to);

    if (fromBlock === toBlock) {
        const [moved] = list.splice(from, 1);
        list.splice(to, 0, moved);

        return normalizeExerciseList(list);
    }

    const [movedBlock] = blocks.splice(fromBlock, 1);
    blocks.splice(toBlock, 0, movedBlock);

    return normalizeExerciseList(blocks.flatMap((block) => block.items.map((item) => item.exercise)));
}

/**
 * Destino da seta (↑ = -1, ↓ = +1) de um exercício, ou `null` quando a seta
 * fica desabilitada. Dentro de um grupo, só anda dentro dele; sozinho, pula
 * o bloco vizinho inteiro.
 */
export function arrowTarget(exercises, index, direction) {
    const blocks = buildExerciseBlocks(exercises);
    const blockIndex = blockIndexOf(blocks, index);
    if (blockIndex === -1) return null;

    const block = blocks[blockIndex];

    if (block.items.length > 1) {
        const position = block.items.findIndex((item) => item.index === index);
        const target = block.items[position + direction];

        return target ? target.index : null;
    }

    const neighbour = blocks[blockIndex + direction];

    return neighbour ? neighbour.items[0].index : null;
}

/**
 * Destino da seta do cabeçalho de um bloco (move o grupo inteiro), ou `null`.
 * Devolve `{ from, to }` pronto para `moveExercise`.
 */
export function blockArrowTarget(exercises, blockIndex, direction) {
    const blocks = buildExerciseBlocks(exercises);
    const block = blocks[blockIndex];
    const neighbour = blocks[blockIndex + direction];

    if (!block || !neighbour) return null;

    return { from: block.items[0].index, to: neighbour.items[0].index };
}
