import { generateUuid } from '@/Utils/offlineStorage';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

/**
 * Edição de uma série JÁ CONCLUÍDA no treino em andamento (Phase 46).
 *
 * Reaproveita exatamente o caminho de "Concluir série": a mesma rota
 * `workout-sessions.sets.store` online e a mesma ação `log_set` na fila
 * offline. Os dois terminam num `updateOrCreate` por `exercise_id` +
 * `set_number` no backend, então reenviar a série corrige o registro
 * existente em vez de criar outro — sem rota nova, sem duplicar.
 *
 * Isolamento: este composable nunca toca em estado de série, cronômetro,
 * descanso nem alarme. Ao salvar, só chama `onSaved` para a página
 * atualizar as cópias locais (card, volume, registro da sessão ativa).
 *
 * @param {object} options
 * @param {() => (number|string)} options.sessionId        ID da sessão (real ou client id offline)
 * @param {() => string} options.clientSessionId           Etiqueta de agrupamento na fila
 * @param {() => boolean} options.offlineOnly              Sessão nasceu offline
 * @param {import('vue').Ref<boolean>} options.isOnline
 * @param {(action: object) => Promise<void>} options.queueAction
 * @param {(exerciseId: number, setNumber: number, values: {weight: ?number, reps: number}) => void} options.onSaved
 */
export function useCompletedSetEdit({ sessionId, clientSessionId, offlineOnly, isOnline, queueAction, onSaved }) {
    // `editing` sobrevive ao fechamento (só `showEdit` muda), para o conteúdo
    // não sumir no meio da animação de saída do modal.
    const editing = ref(null); // { exerciseId, exerciseName, equipment, set: { set_number, weight, reps } }
    const showEdit = ref(false);
    const queueing = ref(false);
    const form = useForm({ exercise_id: null, set_number: null, weight: null, reps: 1 });

    const openEdit = (exercise, set) => {
        form.clearErrors();
        editing.value = {
            exerciseId: exercise.id,
            exerciseName: exercise.name,
            equipment: exercise.equipment ?? null,
            set,
        };
        showEdit.value = true;
    };

    const closeEdit = () => {
        if (form.processing || queueing.value) return;
        showEdit.value = false;
    };

    const finish = (values) => {
        const { exerciseId, set } = editing.value;
        onSaved(exerciseId, set.set_number, values);
        showEdit.value = false;
    };

    const saveEdit = async ({ weight, reps }) => {
        if (!editing.value) return;

        const { exerciseId, set } = editing.value;
        const values = { weight: weight ?? null, reps };

        if (offlineOnly() || !isOnline.value) {
            queueing.value = true;
            try {
                await queueAction({
                    type: 'log_set',
                    client_session_id: clientSessionId(),
                    client_set_id: generateUuid(),
                    session_id: offlineOnly() ? null : sessionId(),
                    occurred_at: new Date().toISOString(),
                    exercise_id: exerciseId,
                    set_number: set.set_number,
                    weight: values.weight,
                    reps: values.reps,
                });
            } finally {
                queueing.value = false;
            }
            finish(values);
            return;
        }

        form.exercise_id = exerciseId;
        form.set_number = set.set_number;
        form.weight = values.weight;
        form.reps = values.reps;
        form.post(route('workout-sessions.sets.store', sessionId()), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => finish(values),
        });
    };

    return { editing, showEdit, form, queueing, openEdit, closeEdit, saveEdit };
}
