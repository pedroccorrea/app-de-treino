<script setup>
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseCard from '@/Components/UI/BaseCard.vue';
import SectionLabel from '@/Components/UI/SectionLabel.vue';
import WorkoutExerciseList from '@/Components/Workouts/WorkoutExerciseList.vue';
import ActiveWorkoutSession from '@/Pages/WorkoutSessions/Active.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useOfflineSync } from '@/Composables/useOfflineSync';
import {
    cacheWorkout,
    generateUuid,
    getActiveSession,
    getActiveSessionSetLogs,
    getCachedLastLogs,
} from '@/Utils/offlineStorage';
import { unlockRestAlarm } from '@/Utils/restAlarm';
import { normalizeExerciseList } from '@/Utils/supersetGroups';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    workout: {
        type: Object,
        required: true,
    },
});

// Grava o detalhe deste treino (com exercícios) no IndexedDB para que ele
// possa ser reaberto e uma sessão iniciada mesmo sem conexão de internet.
onMounted(() => cacheWorkout(props.workout));

// `muscle_groups` pode faltar num registro sintetizado pelo Service Worker
// ou numa versão antiga do cache local — e um `.length` direto em cima de
// `undefined` derruba o render bem no meio do card, deixando na tela só o
// cabeçalho e o botão "Voltar".
const muscleGroups = computed(() => props.workout.muscle_groups ?? []);

// If this page was itself opened from another page (e.g. a program's page)
// via ?return_to=..., the back button and the edit link (which carries
// this page's own URL forward) both honor that origin.
const returnTo = new URLSearchParams(window.location.search).get('return_to');
const currentPath = window.location.pathname + window.location.search;

// `workout.exercises` pode faltar numa resposta sintética do Service Worker
// (`resources/pwa/sw.js`) para uma rota ainda não visitada offline — o
// encadeamento opcional evita que isso quebre o motor reativo do Vue. Os
// grupos combinados passam pela mesma regra do backend; uma ficha de cache
// offline antigo (sem `superset_group`) aparece como sem grupo.
const exercises = ref(normalizeExerciseList(
    [...(props.workout.exercises ?? [])]
        .sort((a, b) => a.order - b.order)
        .map((exercise) => ({ ...exercise })),
));

const hasExercises = computed(() => exercises.value.length > 0);

// A lista (`WorkoutExerciseList`) já devolve a nova ordem sem quebrar grupos;
// o backend normaliza de novo ao gravar.
const onReorder = (list) => {
    exercises.value = list;
    persistOrder();
};

const persistOrder = () => {
    router.patch(
        route('workouts.reorder', props.workout.id),
        { exercise_ids: exercises.value.map((e) => e.id) },
        { preserveScroll: true, preserveState: true },
    );
};

const starting = ref(false);
const { queueAction } = useOfflineSync();

// Sessão iniciada 100% offline: em vez de um painel à parte, renderiza o
// mesmíssimo componente da sessão ativa online (`WorkoutSessions/Active.vue`)
// com dados sintetizados localmente — a tela fica idêntica em ambos os casos,
// e o `start_session` só é confirmado no servidor quando a fila sincronizar.
const offlineSessionProps = ref(null);

/**
 * Monta o conjunto de props de `WorkoutSessions/Active.vue` para uma sessão
 * que vive só neste aparelho. `setLogs` vem do IndexedDB (fila de
 * sincronização + carimbo na sessão ativa), e é isso que faz uma sessão
 * offline sobreviver a sair da tela e voltar depois.
 */
const buildOfflineSessionProps = async (session, setLogs = []) => ({
    session: {
        id: session.id,
        started_at: session.started_at,
        completed_at: null,
        notes: null,
    },
    workout: {
        id: props.workout.id,
        name: props.workout.name,
        description: props.workout.description,
    },
    exercises: exercises.value,
    setLogs,
    lastLogs: await getCachedLastLogs(),
});

// ─── Retomada de sessão offline (Phase 38) ─────────────────────────────────
// Uma sessão iniciada sem internet não existe no servidor, então não há rota
// `/workout-sessions/{id}` para voltar: a tela do treino é renderizada aqui
// mesmo. Ao montar esta página — por navegação normal ou por um clique no
// indicador global "Treino em andamento" — se o IndexedDB disser que há uma
// sessão offline aberta para ESTA ficha, ela é restaurada com o progresso
// intacto. Antes desta correção, sair da tela significava perder o treino.
onMounted(async () => {
    try {
        const session = await getActiveSession();

        if (!session) return;
        if (session.offline_only !== true) return;
        if (String(session.workout_id) !== String(props.workout.id)) return;

        offlineSessionProps.value = await buildOfflineSessionProps(session, await getActiveSessionSetLogs());
    } catch (_) {
        // IndexedDB indisponível: a página da ficha continua funcionando
        // normalmente, apenas sem retomar a sessão automaticamente.
    }
});

const startWorkout = async () => {
    // Toque do usuário: libera o áudio do alarme de descanso para o treino.
    unlockRestAlarm();

    if (!navigator.onLine) {
        starting.value = true;

        const clientSessionId = generateUuid();
        const startedAt = new Date().toISOString();

        await queueAction({
            type: 'start_session',
            client_session_id: clientSessionId,
            occurred_at: startedAt,
            workout_id: props.workout.id,
        });

        offlineSessionProps.value = await buildOfflineSessionProps({
            id: clientSessionId,
            started_at: startedAt,
        });
        starting.value = false;
        return;
    }

    starting.value = true;
    router.post(
        route('workouts.start', props.workout.id),
        {},
        { onFinish: () => { starting.value = false; } },
    );
};
</script>

<template>
    <Head :title="workout.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                <Link
                    :href="returnTo || route('workouts.index')"
                    class="inline-flex items-center gap-1 text-sm font-medium text-text-secondary transition hover:text-accent-text-soft"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Voltar
                </Link>

                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-semibold leading-tight text-text-primary">
                        {{ workout.name }}
                    </h2>
                    <Link
                        :href="route('workouts.edit', { workout: workout.id, return_to: currentPath })"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-radius-sm text-text-secondary transition hover:bg-accent-muted hover:text-accent-text-soft"
                        title="Editar treino"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <BaseCard :padded="false">
                    <!-- Description & Muscle Groups -->
                    <div class="border-b border-border-subtle p-6">
                        <p v-if="workout.description" class="text-sm leading-relaxed text-text-body">
                            {{ workout.description }}
                        </p>
                        <p v-else class="text-sm italic text-text-secondary">
                            Sem descrição para este treino.
                        </p>

                        <div v-if="muscleGroups.length" class="mt-4 flex flex-wrap gap-2">
                            <BaseBadge v-for="muscle in muscleGroups" :key="muscle" tone="neutral">
                                {{ muscle }}
                            </BaseBadge>
                        </div>
                    </div>

                    <!-- Exercises List -->
                    <div class="p-6">
                        <div class="mb-4 flex items-center justify-between">
                            <SectionLabel tone="secondary">
                                Exercícios ({{ exercises.length }})
                            </SectionLabel>
                            <p v-if="hasExercises" class="text-xs text-text-tertiary">
                                Arraste para reordenar
                            </p>
                        </div>

                        <!-- Empty state -->
                        <div
                            v-if="!hasExercises"
                            class="rounded-radius-md border border-dashed border-border-subtle px-4 py-10 text-center"
                        >
                            <p class="text-sm text-text-secondary">
                                Este treino ainda não possui exercícios cadastrados.
                            </p>
                        </div>

                        <WorkoutExerciseList v-else :exercises="exercises" @reorder="onReorder" />
                    </div>

                    <!-- Start Workout Footer -->
                    <div class="border-t border-border-subtle bg-surface-overlay/40 p-6">
                        <BaseButton variant="primary" ripple :disabled="starting" class="w-full" @click="startWorkout">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M12.963 2.286a.75.75 0 00-1.071-.136 9.742 9.742 0 00-3.539 6.176 7.547 7.547 0 01-1.705-1.715.75.75 0 00-1.152-.082A9 9 0 1015.68 4.534a7.46 7.46 0 01-2.717-2.248z" />
                            </svg>
                            {{ starting ? 'Iniciando...' : 'Iniciar Treino' }}
                        </BaseButton>
                        <p class="mt-2 text-center text-xs text-text-secondary">
                            Registre suas séries, repetições, pesos e tempo de descanso em tempo real.
                        </p>
                    </div>
                </BaseCard>
            </div>
        </div>
    </AuthenticatedLayout>

    <ActiveWorkoutSession
        v-if="offlineSessionProps"
        v-bind="offlineSessionProps"
        :offline-only="true"
    />
</template>
