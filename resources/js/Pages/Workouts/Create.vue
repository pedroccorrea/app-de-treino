<script setup>
import WorkoutForm from '@/Components/Workouts/WorkoutForm.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    exercisesCatalog: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    name: '',
    description: '',
    days_of_week: [],
    exercises: [],
});

// The page that opened this create form (e.g. a program's page) can pass
// ?return_to=... so the back button, and the redirect after saving, send
// the user back there instead of always landing on the workouts list.
const returnTo = new URLSearchParams(window.location.search).get('return_to');

const submit = () => {
    form.post(
        returnTo ? route('workouts.store', { return_to: returnTo }) : route('workouts.store'),
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head title="Novo Treino" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                <Link
                    :href="returnTo || route('workouts.index')"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 transition hover:text-violet-600 dark:text-gray-400 dark:hover:text-violet-400"
                >
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 19l-7-7 7-7"
                        />
                    </svg>
                    Voltar
                </Link>

                <h2
                    class="text-xl font-bold leading-tight text-gray-900 dark:text-gray-100"
                >
                    Criar Novo Treino
                </h2>
            </div>
        </template>

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <WorkoutForm
                    :form="form"
                    :exercises-catalog="exercisesCatalog"
                    :cancel-href="returnTo || route('workouts.index')"
                    submit-label="Salvar Treino"
                    processing-label="Salvando Treino..."
                    @submit="submit"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
