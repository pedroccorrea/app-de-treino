<script setup>
import WorkoutForm from '@/Components/Workouts/WorkoutForm.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    workout: {
        type: Object,
        required: true,
    },
    exercisesCatalog: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    name: props.workout.name,
    description: props.workout.description ?? '',
    days_of_week: [...(props.workout.days_of_week ?? [])],
    exercises: [...props.workout.exercises]
        .sort((a, b) => a.order - b.order)
        .map((exercise) => ({
            id: exercise.id,
            name: exercise.name,
            primary_muscle: exercise.primary_muscle,
            target_sets: exercise.target_sets,
            target_reps: exercise.target_reps,
            order: exercise.order,
        })),
});

// The page that opened this edit form (e.g. the workouts list) can pass
// ?redirect_to=... so the backend sends the user back there after saving,
// instead of always landing on the workout's own show page.
const redirectTo = new URLSearchParams(window.location.search).get('redirect_to');

const submit = () => {
    form.put(
        redirectTo
            ? route('workouts.update', { workout: props.workout.id, redirect_to: redirectTo })
            : route('workouts.update', props.workout.id),
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head title="Editar Treino" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                <Link
                    :href="route('workouts.show', workout.id)"
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
                    Voltar ao treino
                </Link>

                <h2
                    class="text-xl font-bold leading-tight text-gray-900 dark:text-gray-100"
                >
                    Editar Treino
                </h2>
            </div>
        </template>

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <WorkoutForm
                    :form="form"
                    :exercises-catalog="exercisesCatalog"
                    :cancel-href="route('workouts.show', workout.id)"
                    submit-label="Salvar Alterações"
                    processing-label="Salvando Alterações..."
                    @submit="submit"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
