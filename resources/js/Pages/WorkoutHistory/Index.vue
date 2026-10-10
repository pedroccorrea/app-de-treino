<script setup>
import BaseButton from '@/Components/UI/BaseButton.vue';
import HistoryEmptyState from '@/Components/WorkoutHistory/HistoryEmptyState.vue';
import HistorySessionCard from '@/Components/WorkoutHistory/HistorySessionCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    // Treinos finalizados, mais recentes primeiro. É uma "merge prop" no
    // backend: cada "Carregar mais" anexa a próxima página a esta lista.
    sessions: {
        type: Array,
        required: true,
    },
    pagination: {
        // { current_page, has_more }
        type: Object,
        required: true,
    },
    // true apenas quando a página foi montada pelo Service Worker sem
    // conexão e sem nenhuma cópia em cache (ver `resources/pwa/sw.js`).
    offline: {
        type: Boolean,
        default: false,
    },
});

const loadingMore = ref(false);

const loadMore = () => {
    if (loadingMore.value || !props.pagination.has_more) return;

    router.reload({
        data: { page: props.pagination.current_page + 1 },
        only: ['sessions', 'pagination'],
        // A URL continua `/history`: um F5 depois recomeça da primeira
        // página em vez de abrir só a página 3, sem as anteriores.
        preserveUrl: true,
        preserveScroll: true,
        preserveState: true,
        onStart: () => (loadingMore.value = true),
        onFinish: () => (loadingMore.value = false),
    });
};

const detailHref = (session) => route('history.show', session.id);
</script>

<template>
    <Head title="Histórico de Treinos" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-text-primary">
                    Histórico
                </h2>
                <p class="mt-0.5 text-sm text-text-secondary">Seus treinos finalizados</p>
            </div>
        </template>

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <HistoryEmptyState
                    v-if="offline"
                    title="Histórico indisponível offline"
                    message="Conecte-se à internet para carregar seus treinos finalizados. Seus treinos registrados offline continuam salvos neste aparelho."
                >
                    <BaseButton variant="secondary" @click="router.visit(route('dashboard'))">Voltar ao início</BaseButton>
                </HistoryEmptyState>

                <HistoryEmptyState
                    v-else-if="sessions.length === 0"
                    title="Nenhum treino finalizado ainda"
                    message="Quando você finalizar um treino, ele aparece aqui com os exercícios e séries que fez."
                >
                    <BaseButton variant="primary" @click="router.visit(route('workouts.index'))">Ver meus treinos</BaseButton>
                </HistoryEmptyState>

                <template v-else>
                    <ol class="space-y-3">
                        <li v-for="session in sessions" :key="session.id">
                            <HistorySessionCard :session="session" :href="detailHref(session)" />
                        </li>
                    </ol>

                    <div v-if="pagination.has_more" class="mt-6 flex justify-center">
                        <BaseButton variant="secondary" :disabled="loadingMore" @click="loadMore">
                            {{ loadingMore ? 'Carregando…' : 'Carregar mais' }}
                        </BaseButton>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
