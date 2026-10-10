<script setup>
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseCard from '@/Components/UI/BaseCard.vue';
import SectionLabel from '@/Components/UI/SectionLabel.vue';
import EditSetModal from '@/Components/WorkoutHistory/EditSetModal.vue';
import HistoryEmptyState from '@/Components/WorkoutHistory/HistoryEmptyState.vue';
import HistoryExerciseCard from '@/Components/WorkoutHistory/HistoryExerciseCard.vue';
import { useOfflineSync } from '@/Composables/useOfflineSync';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    DELETED_WORKOUT_LABEL,
    formatDuration,
    formatSessionDate,
    formatSessionTime,
    formatVolume,
} from '@/Utils/historyFormat';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    // { id, workout_name, completed_at, started_at, duration_minutes,
    //   exercise_count, set_count, volume, exercises: [...] }
    // null apenas na versão montada offline pelo Service Worker.
    session: {
        type: Object,
        default: null,
    },
    offline: {
        type: Boolean,
        default: false,
    },
});

// Mesmo padrão de `Exercises/History.vue`: a origem viaja em ?return_to=...
const returnTo = new URLSearchParams(window.location.search).get('return_to');
const backHref = computed(() => returnTo || route('history.index'));

const title = computed(() => props.session?.workout_name ?? DELETED_WORKOUT_LABEL);

const stats = computed(() => {
    if (!props.session) return [];

    return [
        { label: 'Exercícios', value: props.session.exercise_count },
        { label: 'Séries', value: props.session.set_count },
        { label: 'Volume', value: formatVolume(props.session.volume) },
        { label: 'Duração', value: formatDuration(props.session.duration_minutes) ?? '—' },
    ];
});

// Ao abrir a evolução de um exercício, o "Voltar" de lá traz o usuário de
// volta exatamente para este detalhe.
const exerciseHistoryHref = (exercise) =>
    route('exercises.history', {
        exercise: exercise.exercise_id,
        return_to: window.location.pathname + window.location.search,
    });

// ─── Correções no histórico (Phase 45) — exigem conexão ───────────────────
const { isOnline, refreshAfterHistoryChange } = useOfflineSync();

const OFFLINE_NOTICE = 'Sem conexão com a internet. Editar e excluir no histórico precisa de conexão — nada foi alterado. Tente de novo quando a internet voltar.';

// Aviso exibido no topo da página (exclusões); a edição de série mostra o
// seu dentro do próprio modal, sem fechar o rascunho.
const pageNotice = ref(null);

// Exclusões redirecionam para a origem quando o treino deixa de existir.
const withReturnTo = (params) => (returnTo ? { ...params, return_to: returnTo } : params);

/**
 * Opções de visita comuns às três correções. `navigator.onLine` só pega o
 * caso óbvio; se a conexão cair no meio do envio, o Inertia dispara o evento
 * `exception` (requisição sem NENHUMA resposta HTTP — diferente de um 403 ou
 * 422, que têm resposta) e nunca chama `onSuccess`/`onError`. O listener
 * existe só durante esta visita; `preventDefault` evita que a falha vire um
 * erro não tratado no console. Em sucesso, as cópias deste aparelho (cache
 * do Service Worker, "Última" no IndexedDB) são renovadas.
 */
const correctionVisit = ({ onSuccess = () => {}, onOffline, preserveState = true }) => {
    let networkFailed = false;
    let stopListening = () => {};

    return {
        preserveScroll: true,
        preserveState,
        onStart: () => {
            stopListening = router.on('exception', (event) => {
                event.preventDefault();
                networkFailed = true;
            });
        },
        onSuccess: () => {
            onSuccess();
            refreshAfterHistoryChange();
        },
        onFinish: () => {
            stopListening();
            if (networkFailed) onOffline();
        },
    };
};

// Editar série. `editing` sobrevive ao fechamento (só `showEdit` muda), para
// o conteúdo não sumir no meio da animação de saída do modal.
const editing = ref(null); // { exercise, set }
const showEdit = ref(false);
const setForm = useForm({ weight: null, reps: 1 });
const editNotice = ref(null);

const openEditSet = (exercise, set) => {
    setForm.clearErrors();
    editNotice.value = null;
    editing.value = { exercise, set };
    showEdit.value = true;
};

const closeEditSet = () => {
    if (setForm.processing) return;
    showEdit.value = false;
};

const saveSet = ({ weight, reps }) => {
    editNotice.value = null;
    if (!isOnline.value) {
        editNotice.value = OFFLINE_NOTICE;
        return;
    }

    setForm.weight = weight;
    setForm.reps = reps;
    setForm.patch(
        route('history.sets.update', { session: props.session.id, setLog: editing.value.set.id }),
        correctionVisit({
            onSuccess: () => (showEdit.value = false),
            onOffline: () => (editNotice.value = OFFLINE_NOTICE),
        }),
    );
};

// Excluir exercício / treino: uma confirmação só, que diz o que sai.
// Mesmo motivo de `showEdit`: o texto não pode trocar durante a saída.
const pendingDelete = ref(null); // { kind: 'exercise', exercise } | { kind: 'session' }
const showDelete = ref(false);
const deleting = ref(false);

const isLastExercise = computed(() => props.session?.exercises.length === 1);

const confirmation = computed(() => {
    if (pendingDelete.value?.kind === 'exercise') {
        const { name, sets } = pendingDelete.value.exercise;
        const lastNote = isLastExercise.value
            ? ' É o único exercício deste treino, então o treino inteiro também sai do histórico.'
            : '';

        return {
            title: 'Excluir exercício deste treino?',
            description: `${sets.length === 1 ? 'A série' : `As ${sets.length} séries`} de "${name}" deste treino serão excluídas e deixarão de contar no volume, na referência "Última", nos recordes e nas sugestões de carga.${lastNote} A ficha e o exercício do catálogo continuam como estão.`,
            confirmText: 'Excluir exercício',
        };
    }

    return {
        title: 'Excluir este treino?',
        description: `O treino "${title.value}" de ${props.session ? formatSessionDate(props.session.completed_at) : ''} sai do histórico e de todos os cálculos (volume, "Última", recordes, sugestões de carga e sequência de dias). A ficha não é excluída.`,
        confirmText: 'Excluir treino',
    };
});

const askDelete = (target) => {
    pageNotice.value = null;
    pendingDelete.value = target;
    showDelete.value = true;
};

const cancelDelete = () => {
    if (deleting.value) return;
    showDelete.value = false;
};

const showOfflineNotice = () => {
    showDelete.value = false;
    pageNotice.value = OFFLINE_NOTICE;
};

const confirmDelete = () => {
    if (!isOnline.value) {
        showOfflineNotice();
        return;
    }

    const isExercise = pendingDelete.value.kind === 'exercise';
    const url = isExercise
        ? route('history.exercises.destroy', withReturnTo({ session: props.session.id, exercise: pendingDelete.value.exercise.exercise_id }))
        : route('history.destroy', withReturnTo({ session: props.session.id }));

    const options = correctionVisit({
        onSuccess: () => (showDelete.value = false),
        onOffline: showOfflineNotice,
        // Excluir o último exercício leva o treino junto e redireciona para
        // fora desta página: não há estado para preservar.
        preserveState: isExercise && !isLastExercise.value,
    });

    deleting.value = true;
    router.delete(url, {
        ...options,
        onFinish: () => {
            deleting.value = false;
            options.onFinish();
        },
    });
};
</script>

<template>
    <Head :title="`Histórico · ${title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                <Link
                    :href="backHref"
                    class="inline-flex min-h-[48px] items-center gap-1 self-start text-sm font-medium text-text-secondary transition hover:text-accent-text-soft sm:self-auto"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Voltar
                </Link>

                <div v-if="session" class="min-w-0">
                    <h2
                        class="truncate text-xl font-semibold leading-tight"
                        :class="session.workout_name ? 'text-text-primary' : 'italic text-text-secondary'"
                    >
                        {{ title }}
                    </h2>
                    <p class="mt-0.5 text-sm text-text-secondary">
                        {{ formatSessionDate(session.completed_at) }} · {{ formatSessionTime(session.completed_at) }}
                    </p>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8">
                <HistoryEmptyState
                    v-if="!session || offline"
                    title="Treino indisponível offline"
                    message="Conecte-se à internet para ver os detalhes deste treino."
                >
                    <BaseButton variant="secondary" @click="router.visit(route('history.index'))">Voltar ao histórico</BaseButton>
                </HistoryEmptyState>

                <template v-else>
                    <p
                        v-if="pageNotice"
                        role="alert"
                        class="rounded-radius-md border border-warning px-4 py-3 text-sm text-warning"
                    >
                        {{ pageNotice }}
                    </p>

                    <BaseCard>
                        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <div v-for="stat in stats" :key="stat.label">
                                <dt><SectionLabel>{{ stat.label }}</SectionLabel></dt>
                                <dd class="mt-1 text-lg font-medium text-text-primary [font-variant-numeric:tabular-nums]">
                                    {{ stat.value }}
                                </dd>
                            </div>
                        </dl>
                    </BaseCard>

                    <div>
                        <SectionLabel>Exercícios</SectionLabel>
                        <p class="mt-1 text-xs text-text-tertiary">Toque em uma série para corrigir carga ou repetições.</p>

                        <p v-if="session.exercises.length === 0" class="mt-3 text-sm text-text-secondary">
                            Nenhuma série registrada neste treino.
                        </p>

                        <ol v-else class="mt-3 space-y-3">
                            <li v-for="exercise in session.exercises" :key="exercise.exercise_id">
                                <HistoryExerciseCard
                                    :exercise="exercise"
                                    :history-href="exerciseHistoryHref(exercise)"
                                    editable
                                    @edit-set="openEditSet(exercise, $event)"
                                    @delete="askDelete({ kind: 'exercise', exercise })"
                                />
                            </li>
                        </ol>
                    </div>

                    <BaseButton variant="danger" class="w-full sm:w-auto" @click="askDelete({ kind: 'session' })">
                        Excluir treino do histórico
                    </BaseButton>
                </template>
            </div>
        </div>

        <EditSetModal
            :show="showEdit"
            :set="editing?.set ?? null"
            :exercise-name="editing?.exercise.name ?? ''"
            :equipment="editing?.exercise.equipment ?? null"
            :processing="setForm.processing"
            :errors="setForm.errors"
            :notice="editNotice"
            @save="saveSet"
            @close="closeEditSet"
        />

        <ConfirmationModal
            :show="showDelete"
            :title="confirmation.title"
            :description="confirmation.description"
            :confirm-text="confirmation.confirmText"
            :processing="deleting"
            @cancel="cancelDelete"
            @confirm="confirmDelete"
        />
    </AuthenticatedLayout>
</template>
