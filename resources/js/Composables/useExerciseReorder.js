import {
    arrowTarget,
    blockArrowTarget,
    moveExercise,
} from '@/Utils/supersetGroups';
import { ref } from 'vue';

// Não inicia o arrastar quando o toque começa em um elemento interativo.
const DRAG_IGNORE = ['INPUT', 'TEXTAREA', 'SELECT', 'BUTTON', 'A', 'LABEL'];

const isDraggableTarget = (event) => {
    let el = event.target;
    while (el && el !== event.currentTarget) {
        if (DRAG_IGNORE.includes(el.tagName)) return false;
        el = el.parentElement;
    }
    return true;
};

const sameOrder = (a, b) => a.length === b.length && a.every((exercise, i) => exercise.id === b[i].id);

/**
 * Arrastar e soltar + setas da lista de exercícios de uma ficha, respeitando
 * os grupos combinados (regra em `Utils/supersetGroups.js`): um exercício de
 * fora nunca cai no meio de um grupo, e mover um exercício do grupo para fora
 * leva o bloco inteiro.
 *
 * `getExercises` devolve a lista atual; `commit(novaLista)` só é chamado
 * quando a ordem realmente mudou (a página da ficha grava a cada commit).
 */
export function useExerciseReorder(getExercises, commit) {
    const draggingIndex = ref(null);
    const dragOverIndex = ref(null);

    const apply = (from, to) => {
        const current = getExercises();
        const next = moveExercise(current, from, to);

        if (!sameOrder(current, next)) commit(next);
    };

    const onDragStart = (event, index) => {
        if (!isDraggableTarget(event)) {
            event.preventDefault();
            return;
        }
        draggingIndex.value = index;
    };

    const onDragOver = (event, index) => {
        event.preventDefault();
        dragOverIndex.value = index;
    };

    const onDragEnd = () => {
        draggingIndex.value = null;
        dragOverIndex.value = null;
    };

    const onDrop = (targetIndex) => {
        const from = draggingIndex.value;
        onDragEnd();

        if (from === null || from === targetIndex) return;
        apply(from, targetIndex);
    };

    const canMove = (index, direction) => arrowTarget(getExercises(), index, direction) !== null;

    const move = (index, direction) => {
        const target = arrowTarget(getExercises(), index, direction);
        if (target !== null) apply(index, target);
    };

    const canMoveBlock = (blockIndex, direction) => blockArrowTarget(getExercises(), blockIndex, direction) !== null;

    const moveBlock = (blockIndex, direction) => {
        const target = blockArrowTarget(getExercises(), blockIndex, direction);
        if (target) apply(target.from, target.to);
    };

    return {
        draggingIndex,
        dragOverIndex,
        onDragStart,
        onDragOver,
        onDragEnd,
        onDrop,
        canMove,
        move,
        canMoveBlock,
        moveBlock,
    };
}
