<script setup>
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseCard from '@/Components/UI/BaseCard.vue';
import SectionLabel from '@/Components/UI/SectionLabel.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    workout: {
        type: Object,
        required: true,
    },
});

// If this page was itself opened from another page (e.g. a program's page)
// via ?return_to=..., the back button and the edit link (which carries
// this page's own URL forward) both honor that origin.
const returnTo = new URLSearchParams(window.location.search).get('return_to');
const currentPath = window.location.pathname + window.location.search;

// Drag & Drop state
const exercises = ref([...props.workout.exercises].sort((a, b) => a.order - b.order));
const draggingIndex = ref(null);
const dragOverIndex = ref(null);

const formatTarget = (exercise) => {
    const parts = [];
    if (exercise.target_sets) {
        parts.push(`${exercise.target_sets} ${exercise.target_sets === 1 ? 'série' : 'séries'}`);
    }
    if (exercise.target_reps) {
        parts.push(exercise.target_reps + ' reps');
    }
    return parts.length ? parts.join(' × ') : 'Metas não definidas';
};

const hasExercises = computed(() => exercises.value.length > 0);

// --- Drag & Drop handlers ---
// We only start a drag if the pointer-down target is NOT an interactive element
const DRAG_IGNORE = ['INPUT', 'TEXTAREA', 'SELECT', 'BUTTON', 'A', 'LABEL'];

const isDraggableTarget = (event) => {
    let el = event.target;
    while (el && el !== event.currentTarget) {
        if (DRAG_IGNORE.includes(el.tagName)) return false;
        el = el.parentElement;
    }
    return true;
};

const onDragStart = (event, index) => {
    if (!isDraggableTarget(event)) {
        event.preventDefault();
        return;
    }
    draggingIndex.value = index;
};

const onDragOver = (e, index) => {
    e.preventDefault();
    dragOverIndex.value = index;
};

const onDrop = (targetIndex) => {
    if (draggingIndex.value === null || draggingIndex.value === targetIndex) {
        draggingIndex.value = null;
        dragOverIndex.value = null;
        return;
    }
    const reordered = [...exercises.value];
    const [moved] = reordered.splice(draggingIndex.value, 1);
    reordered.splice(targetIndex, 0, moved);
    exercises.value = reordered;
    draggingIndex.value = null;
    dragOverIndex.value = null;
    persistOrder();
};

const onDragEnd = () => {
    draggingIndex.value = null;
    dragOverIndex.value = null;
};

// --- Reorder buttons (↑ / ↓) ---
const moveUp = (index) => {
    if (index === 0) return;
    const arr = [...exercises.value];
    [arr[index - 1], arr[index]] = [arr[index], arr[index - 1]];
    exercises.value = arr;
    persistOrder();
};

const moveDown = (index) => {
    if (index === exercises.value.length - 1) return;
    const arr = [...exercises.value];
    [arr[index], arr[index + 1]] = [arr[index + 1], arr[index]];
    exercises.value = arr;
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

const startWorkout = () => {
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

                        <div v-if="workout.muscle_groups.length" class="mt-4 flex flex-wrap gap-2">
                            <BaseBadge v-for="muscle in workout.muscle_groups" :key="muscle" tone="neutral">
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

                        <!-- Draggable list — the entire card is the drag target -->
                        <ol v-else class="space-y-2">
                            <li
                                v-for="(exercise, index) in exercises"
                                :key="exercise.id"
                                draggable="true"
                                @dragstart="(e) => onDragStart(e, index)"
                                @dragover="(e) => onDragOver(e, index)"
                                @drop="onDrop(index)"
                                @dragend="onDragEnd"
                                :class="[
                                    'group flex items-center gap-3 rounded-radius-md border p-4 transition-all duration-200',
                                    dragOverIndex === index && draggingIndex !== index
                                        ? 'border-accent bg-accent-muted cursor-copy'
                                        : 'border-border-subtle bg-surface-overlay/40 hover:border-border-accent cursor-grab active:cursor-grabbing',
                                    draggingIndex === index ? 'opacity-40 scale-[0.98]' : 'opacity-100',
                                ]"
                            >
                                <!-- Order number -->
                                <span
                                    class="flex h-7 w-7 shrink-0 select-none items-center justify-center rounded-radius-full bg-accent-muted text-xs font-bold text-accent-label"
                                >
                                    {{ index + 1 }}
                                </span>

                                <!-- Exercise info -->
                                <div class="min-w-0 flex-1 select-none">
                                    <p class="font-medium text-text-primary">
                                        {{ exercise.name }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-text-secondary">
                                        {{ exercise.primary_muscle }}
                                    </p>
                                    <p class="mt-1.5 text-sm font-semibold text-accent-text-soft">
                                        {{ formatTarget(exercise) }}
                                    </p>
                                </div>

                                <!-- Reorder buttons -->
                                <div class="flex flex-col gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
                                    <button
                                        type="button"
                                        @click.stop="moveUp(index)"
                                        :disabled="index === 0"
                                        class="rounded-radius-sm p-1 text-text-secondary transition hover:bg-accent-muted hover:text-accent-text-soft disabled:cursor-not-allowed disabled:opacity-20"
                                        title="Mover para cima"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7" />
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        @click.stop="moveDown(index)"
                                        :disabled="index === exercises.length - 1"
                                        class="rounded-radius-sm p-1 text-text-secondary transition hover:bg-accent-muted hover:text-accent-text-soft disabled:cursor-not-allowed disabled:opacity-20"
                                        title="Mover para baixo"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                </div>
                            </li>
                        </ol>
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
</template>
