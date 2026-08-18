<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    catalog: {
        type: Array,
        default: () => [],
    },
    selectedIds: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close', 'select', 'toggle', 'remove']);

const searchQuery = ref('');
const selectedMuscleKey = ref(null);

const muscleMeta = {
    chest: {
        icon: '🏋️‍♂️',
        gradient: 'from-blue-500/20 to-indigo-500/20 text-blue-600 dark:text-blue-400 border-blue-500/30',
        bgPill: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
    },
    back: {
        icon: '🛡️',
        gradient: 'from-emerald-500/20 to-teal-500/20 text-emerald-600 dark:text-emerald-400 border-emerald-500/30',
        bgPill: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    },
    shoulders: {
        icon: '📐',
        gradient: 'from-amber-500/20 to-orange-500/20 text-amber-600 dark:text-amber-400 border-amber-500/30',
        bgPill: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    },
    biceps: {
        icon: '💪',
        gradient: 'from-violet-500/20 to-purple-500/20 text-violet-600 dark:text-violet-400 border-violet-500/30',
        bgPill: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
    },
    triceps: {
        icon: '⚡',
        gradient: 'from-fuchsia-500/20 to-pink-500/20 text-fuchsia-600 dark:text-fuchsia-400 border-fuchsia-500/30',
        bgPill: 'bg-fuchsia-500/10 text-fuchsia-600 dark:text-fuchsia-400',
    },
    quads: {
        icon: '🦵',
        gradient: 'from-rose-500/20 to-red-500/20 text-rose-600 dark:text-rose-400 border-rose-500/30',
        bgPill: 'bg-rose-500/10 text-rose-600 dark:text-rose-400',
    },
    hamstrings: {
        icon: '🏃',
        gradient: 'from-cyan-500/20 to-sky-500/20 text-cyan-600 dark:text-cyan-400 border-cyan-500/30',
        bgPill: 'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400',
    },
    glutes: {
        icon: '🍑',
        gradient: 'from-orange-500/20 to-amber-500/20 text-orange-600 dark:text-orange-400 border-orange-500/30',
        bgPill: 'bg-orange-500/10 text-orange-600 dark:text-orange-400',
    },
    calves: {
        icon: '👟',
        gradient: 'from-lime-500/20 to-green-500/20 text-lime-600 dark:text-lime-400 border-lime-500/30',
        bgPill: 'bg-lime-500/10 text-lime-600 dark:text-lime-400',
    },
    abs: {
        icon: '🧱',
        gradient: 'from-yellow-500/20 to-amber-500/20 text-yellow-600 dark:text-yellow-400 border-yellow-500/30',
        bgPill: 'bg-yellow-500/10 text-yellow-600 dark:text-yellow-400',
    },
    forearms: {
        icon: '✊',
        gradient: 'from-teal-500/20 to-emerald-500/20 text-teal-600 dark:text-teal-400 border-teal-500/30',
        bgPill: 'bg-teal-500/10 text-teal-600 dark:text-teal-400',
    },
    traps: {
        icon: '🦅',
        gradient: 'from-purple-500/20 to-indigo-500/20 text-purple-600 dark:text-purple-400 border-purple-500/30',
        bgPill: 'bg-purple-500/10 text-purple-600 dark:text-purple-400',
    },
};

const defaultMeta = {
    icon: '🎯',
    gradient: 'from-violet-500/20 to-indigo-500/20 text-violet-600 dark:text-violet-400 border-violet-500/30',
    bgPill: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
};

const getMeta = (key) => muscleMeta[key] || defaultMeta;

// Flattened list of all exercises with group information
const allExercises = computed(() =>
    props.catalog.flatMap((group) =>
        group.exercises.map((exercise) => ({
            ...exercise,
            muscle_key: group.muscle_key,
            muscle: group.muscle,
        })),
    ),
);

// Enhanced catalog with counts of selected items
const muscleGroupsWithCounts = computed(() =>
    props.catalog.map((group) => {
        const meta = getMeta(group.muscle_key);
        const selectedInGroup = group.exercises.filter((exercise) =>
            props.selectedIds.includes(exercise.id),
        ).length;

        return {
            ...group,
            icon: meta.icon,
            gradient: meta.gradient,
            bgPill: meta.bgPill,
            totalExercises: group.exercises.length,
            selectedCount: selectedInGroup,
        };
    }),
);

// Active group object
const currentGroup = computed(() => {
    if (!selectedMuscleKey.value) return null;
    return muscleGroupsWithCounts.value.find(
        (g) => g.muscle_key === selectedMuscleKey.value,
    );
});

// Exercises in currently active group (with optional search filter inside group)
const currentGroupExercises = computed(() => {
    if (!currentGroup.value) return [];
    let list = currentGroup.value.exercises;
    const query = searchQuery.value.trim().toLowerCase();
    if (query) {
        list = list.filter((e) => e.name.toLowerCase().includes(query));
    }
    return list;
});

// Global search results (when typing search from groups view)
const globalSearchResults = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    if (!query) return [];
    return allExercises.value.filter((exercise) =>
        exercise.name.toLowerCase().includes(query),
    );
});

const isSelected = (exerciseId) => props.selectedIds.includes(exerciseId);

const toggleExercise = (exercise) => {
    if (isSelected(exercise.id)) {
        emit('toggle', exercise);
        emit('remove', exercise);
    } else {
        emit('toggle', exercise);
        emit('select', exercise);
    }
};

const openGroup = (muscleKey) => {
    selectedMuscleKey.value = muscleKey;
};

const backToGroups = () => {
    selectedMuscleKey.value = null;
    searchQuery.value = '';
};

const close = () => {
    searchQuery.value = '';
    selectedMuscleKey.value = null;
    emit('close');
};

const clearSearch = () => {
    searchQuery.value = '';
};

// Handle Escape key
const onKeydown = (e) => {
    if (e.key === 'Escape' && props.show) {
        if (searchQuery.value) {
            searchQuery.value = '';
        } else if (selectedMuscleKey.value) {
            backToGroups();
        } else {
            close();
        }
    }
};

watch(
    () => props.show,
    (isOpen) => {
        if (isOpen) {
            document.body.style.overflow = 'hidden';
            window.addEventListener('keydown', onKeydown);
        } else {
            document.body.style.overflow = '';
            window.removeEventListener('keydown', onKeydown);
            selectedMuscleKey.value = null;
            searchQuery.value = '';
        }
    },
);

onMounted(() => {
    if (props.show) {
        document.body.style.overflow = 'hidden';
        window.addEventListener('keydown', onKeydown);
    }
});

onUnmounted(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-2"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-50 flex flex-col bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100"
            >
                <!-- TOP HEADER -->
                <header
                    class="sticky top-0 z-20 border-b border-gray-200 bg-white/90 px-4 py-3.5 backdrop-blur-md dark:border-gray-800 dark:bg-gray-900/90 sm:px-6"
                >
                    <div class="mx-auto flex max-w-5xl items-center justify-between gap-3">
                        <!-- Left: Back Button or Close -->
                        <div class="flex items-center gap-3">
                            <button
                                v-if="selectedMuscleKey"
                                type="button"
                                @click="backToGroups"
                                class="inline-flex items-center gap-2 rounded-xl bg-violet-50 px-3.5 py-2 text-sm font-semibold text-violet-700 transition hover:bg-violet-100 dark:bg-violet-950/50 dark:text-violet-300 dark:hover:bg-violet-900/50 focus:outline-none focus:ring-2 focus:ring-violet-500"
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
                                        stroke-width="2.5"
                                        d="M15 19l-7-7 7-7"
                                    />
                                </svg>
                                <span>Voltar aos Grupos</span>
                            </button>

                            <button
                                v-else
                                type="button"
                                @click="close"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
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
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                                <span>Fechar</span>
                            </button>

                            <!-- Breadcrumb & Title -->
                            <div class="hidden sm:block">
                                <div
                                    v-if="selectedMuscleKey"
                                    class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    <button
                                        type="button"
                                        @click="backToGroups"
                                        class="hover:underline hover:text-violet-600 dark:hover:text-violet-400"
                                    >
                                        Grupos Musculares
                                    </button>
                                    <span>/</span>
                                    <span class="font-medium text-gray-800 dark:text-gray-200">
                                        {{ currentGroup?.muscle }}
                                    </span>
                                </div>
                                <h1
                                    v-else
                                    class="text-base font-bold text-gray-900 dark:text-gray-100"
                                >
                                    Adicionar Exercícios
                                </h1>
                            </div>
                        </div>

                        <!-- Center: Group Title on Mobile -->
                        <div class="block sm:hidden text-center truncate">
                            <h2 class="text-sm font-bold truncate">
                                {{ selectedMuscleKey ? currentGroup?.muscle : 'Grupos Musculares' }}
                            </h2>
                        </div>

                        <!-- Right: Selected Count + Conclude button -->
                        <div class="flex items-center gap-2 sm:gap-3">
                            <span
                                v-if="selectedIds.length > 0"
                                class="inline-flex items-center gap-1 rounded-full bg-violet-500/10 px-2.5 py-1 text-xs font-semibold text-violet-600 dark:text-violet-400"
                            >
                                <svg
                                    class="h-3.5 w-3.5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2.5"
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                                {{ selectedIds.length }} no treino
                            </span>

                            <button
                                type="button"
                                @click="close"
                                class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white shadow-md shadow-violet-500/20 transition hover:bg-violet-700 hover:shadow-violet-500/30 focus:outline-none focus:ring-2 focus:ring-violet-500"
                            >
                                <span>Concluir</span>
                            </button>
                        </div>
                    </div>
                </header>

                <!-- SEARCH BAR -->
                <div
                    class="border-b border-gray-200 bg-white/70 px-4 py-3 backdrop-blur-sm dark:border-gray-800 dark:bg-gray-900/70 sm:px-6"
                >
                    <div class="mx-auto max-w-5xl">
                        <div class="relative">
                            <svg
                                class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                />
                            </svg>

                            <input
                                v-model="searchQuery"
                                type="search"
                                :placeholder="
                                    selectedMuscleKey
                                        ? `Buscar exercício em ${currentGroup?.muscle}...`
                                        : 'Buscar exercício por nome (ex: Supino, Agachamento, Rosca)...'
                                "
                                class="block w-full rounded-2xl border-gray-200 bg-gray-50/80 py-3 pl-11 pr-10 text-sm shadow-inner transition focus:border-violet-500 focus:bg-white focus:ring-2 focus:ring-violet-500/20 dark:border-gray-700 dark:bg-gray-800/80 dark:text-gray-200 dark:focus:border-violet-500 dark:focus:bg-gray-800"
                            />

                            <button
                                v-if="searchQuery"
                                type="button"
                                @click="clearSearch"
                                class="absolute right-3 top-1/2 -translate-y-1/2 rounded-full p-1 text-gray-400 hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                                title="Limpar busca"
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
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- MAIN SCROLLABLE CONTENT -->
                <main class="flex-1 overflow-y-auto px-4 py-6 sm:px-6">
                    <div class="mx-auto max-w-5xl">
                        <!-- VIEW 1: GLOBAL SEARCH RESULTS (when user is searching without group or typing globally) -->
                        <div v-if="searchQuery && !selectedMuscleKey" class="space-y-4">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                    Resultados para "<span class="font-semibold text-gray-900 dark:text-gray-100">{{ searchQuery }}</span>"
                                    <span class="ml-1 text-xs">({{ globalSearchResults.length }} encontrados)</span>
                                </p>
                                <button
                                    type="button"
                                    @click="clearSearch"
                                    class="text-xs font-semibold text-violet-600 hover:underline dark:text-violet-400"
                                >
                                    Limpar e ver grupos
                                </button>
                            </div>

                            <div
                                v-if="globalSearchResults.length === 0"
                                class="rounded-2xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-800"
                            >
                                <p class="text-base font-medium text-gray-600 dark:text-gray-400">
                                    Nenhum exercício encontrado com esse nome.
                                </p>
                                <p class="mt-1 text-xs text-gray-500">
                                    Tente buscar por outro termo ou escolha um grupo muscular abaixo.
                                </p>
                                <button
                                    type="button"
                                    @click="clearSearch"
                                    class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-violet-600 px-4 py-2 text-xs font-semibold text-white"
                                >
                                    Ver todos os grupos
                                </button>
                            </div>

                            <div v-else class="grid gap-3 sm:grid-cols-2">
                                <div
                                    v-for="exercise in globalSearchResults"
                                    :key="exercise.id"
                                    class="flex items-center justify-between gap-3 rounded-2xl border p-4 transition"
                                    :class="
                                        isSelected(exercise.id)
                                            ? 'border-violet-500/40 bg-violet-50/50 dark:border-violet-500/30 dark:bg-violet-950/20'
                                            : 'border-gray-200 bg-white hover:border-violet-400/50 hover:shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:hover:border-violet-500/40'
                                    "
                                >
                                    <div class="min-w-0 flex-1">
                                        <p class="font-semibold text-gray-900 dark:text-gray-100 truncate">
                                            {{ exercise.name }}
                                        </p>
                                        <div class="mt-1 flex items-center gap-2">
                                            <span
                                                class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                                :class="getMeta(exercise.muscle_key).bgPill"
                                            >
                                                {{ exercise.muscle }}
                                            </span>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        @click="toggleExercise(exercise)"
                                        class="shrink-0 inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-semibold transition focus:outline-none focus:ring-2 focus:ring-violet-500"
                                        :class="
                                            isSelected(exercise.id)
                                                ? 'bg-violet-600 text-white shadow-sm hover:bg-violet-700'
                                                : 'bg-gray-100 text-gray-800 hover:bg-violet-50 hover:text-violet-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-violet-900/40 dark:hover:text-violet-300'
                                        "
                                    >
                                        <template v-if="isSelected(exercise.id)">
                                            <svg
                                                class="h-3.5 w-3.5"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2.5"
                                                    d="M5 13l4 4L19 7"
                                                />
                                            </svg>
                                            <span>Adicionado</span>
                                        </template>
                                        <template v-else>
                                            <svg
                                                class="h-3.5 w-3.5"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M12 4v16m8-8H4"
                                                />
                                            </svg>
                                            <span>Adicionar</span>
                                        </template>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- VIEW 2: MUSCLE GROUPS GRID (Step 1) -->
                        <div v-else-if="!selectedMuscleKey" class="space-y-6">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                                <div>
                                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">
                                        Escolha um Grupo Muscular
                                    </h2>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        Selecione o grupo para visualizar os exercícios disponíveis.
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                <button
                                    v-for="group in muscleGroupsWithCounts"
                                    :key="group.muscle_key"
                                    type="button"
                                    @click="openGroup(group.muscle_key)"
                                    class="group relative flex items-center justify-between rounded-2xl border border-gray-200 bg-white p-4 text-left shadow-sm transition duration-150 hover:-translate-y-0.5 hover:border-violet-500/50 hover:shadow-md dark:border-gray-800 dark:bg-gray-900 dark:hover:border-violet-500/40"
                                >
                                    <div class="flex items-center gap-3.5 min-w-0">
                                        <div
                                            class="flex h-13 w-13 shrink-0 items-center justify-center rounded-2xl border bg-gradient-to-br text-2xl shadow-inner transition group-hover:scale-105"
                                            :class="group.gradient"
                                        >
                                            {{ group.icon }}
                                        </div>

                                        <div class="min-w-0">
                                            <p class="font-bold text-gray-900 group-hover:text-violet-600 dark:text-gray-100 dark:group-hover:text-violet-400 transition truncate">
                                                {{ group.muscle }}
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ group.totalExercises }} {{ group.totalExercises === 1 ? 'exercício' : 'exercícios' }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 pl-2">
                                        <span
                                            v-if="group.selectedCount > 0"
                                            class="inline-flex shrink-0 items-center gap-1 rounded-full bg-violet-500/15 px-2.5 py-1 text-xs font-bold text-violet-600 dark:text-violet-400"
                                        >
                                            <svg
                                                class="h-3 w-3"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="3"
                                                    d="M5 13l4 4L19 7"
                                                />
                                            </svg>
                                            {{ group.selectedCount }}
                                        </span>

                                        <svg
                                            class="h-5 w-5 text-gray-400 transition group-hover:translate-x-1 group-hover:text-violet-500 dark:text-gray-600"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M9 5l7 7-7 7"
                                            />
                                        </svg>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- VIEW 3: EXERCISES OF SELECTED GROUP (Step 2) -->
                        <div v-else class="space-y-6">
                            <!-- Prominent Sub-Header Banner with Visible Back Button -->
                            <div
                                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                            >
                                <div class="flex items-center gap-3.5">
                                    <div
                                        class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl border bg-gradient-to-br text-3xl shadow-inner"
                                        :class="currentGroup?.gradient"
                                    >
                                        {{ currentGroup?.icon }}
                                    </div>

                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">
                                                {{ currentGroup?.muscle }}
                                            </h2>
                                            <span
                                                v-if="currentGroup?.selectedCount > 0"
                                                class="rounded-full bg-violet-500/10 px-2.5 py-0.5 text-xs font-semibold text-violet-600 dark:text-violet-400"
                                            >
                                                {{ currentGroup?.selectedCount }} no treino
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ currentGroup?.totalExercises }} exercícios disponíveis neste grupo
                                        </p>
                                    </div>
                                </div>

                                <!-- HIGHLY VISIBLE BACK BUTTON -->
                                <button
                                    type="button"
                                    @click="backToGroups"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-violet-500/30 bg-violet-50 px-4 py-2.5 text-sm font-bold text-violet-700 transition hover:bg-violet-100 hover:border-violet-500 dark:bg-violet-950/40 dark:border-violet-500/40 dark:text-violet-300 dark:hover:bg-violet-900/60"
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
                                            stroke-width="2.5"
                                            d="M15 19l-7-7 7-7"
                                        />
                                    </svg>
                                    <span>← Escolher outro grupo</span>
                                </button>
                            </div>

                            <!-- Exercise Cards List -->
                            <div class="space-y-3">
                                <div
                                    v-if="currentGroupExercises.length === 0"
                                    class="rounded-2xl border border-dashed border-gray-300 py-12 text-center dark:border-gray-800"
                                >
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Nenhum exercício encontrado em {{ currentGroup?.muscle }}.
                                    </p>
                                    <button
                                        v-if="searchQuery"
                                        type="button"
                                        @click="clearSearch"
                                        class="mt-3 text-xs font-semibold text-violet-600 hover:underline dark:text-violet-400"
                                    >
                                        Limpar filtro de busca
                                    </button>
                                </div>

                                <div
                                    v-for="exercise in currentGroupExercises"
                                    :key="exercise.id"
                                    class="flex items-center justify-between gap-4 rounded-2xl border p-4 transition duration-150"
                                    :class="
                                        isSelected(exercise.id)
                                            ? 'border-violet-500/40 bg-violet-50/60 shadow-sm dark:border-violet-500/40 dark:bg-violet-950/25'
                                            : 'border-gray-200 bg-white hover:border-violet-400/50 hover:shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:hover:border-violet-500/30'
                                    "
                                >
                                    <div class="min-w-0 flex-1">
                                        <p class="text-base font-semibold text-gray-900 dark:text-gray-100">
                                            {{ exercise.name }}
                                        </p>
                                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                            Grupo principal: <span class="font-medium text-gray-700 dark:text-gray-300">{{ exercise.primary_muscle }}</span>
                                        </p>
                                    </div>

                                    <button
                                        type="button"
                                        @click="toggleExercise(exercise)"
                                        class="shrink-0 inline-flex items-center gap-1.5 rounded-xl px-4 py-2.5 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-violet-500"
                                        :class="
                                            isSelected(exercise.id)
                                                ? 'bg-violet-600 text-white shadow-md shadow-violet-500/20 hover:bg-violet-700'
                                                : 'bg-gray-100 text-gray-800 hover:bg-violet-50 hover:text-violet-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-violet-900/40 dark:hover:text-violet-300'
                                        "
                                    >
                                        <template v-if="isSelected(exercise.id)">
                                            <svg
                                                class="h-4 w-4"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2.5"
                                                    d="M5 13l4 4L19 7"
                                                />
                                            </svg>
                                            <span>Adicionado</span>
                                        </template>
                                        <template v-else>
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
                                                    d="M12 4v16m8-8H4"
                                                />
                                            </svg>
                                            <span>Adicionar</span>
                                        </template>
                                    </button>
                                </div>
                            </div>

                            <!-- Bottom Group Navigation Assist -->
                            <div class="pt-4 flex items-center justify-between border-t border-gray-200 dark:border-gray-800">
                                <button
                                    type="button"
                                    @click="backToGroups"
                                    class="inline-flex items-center gap-2 text-sm font-semibold text-violet-600 hover:text-violet-700 dark:text-violet-400 dark:hover:text-violet-300"
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
                                            stroke-width="2.5"
                                            d="M15 19l-7-7 7-7"
                                        />
                                    </svg>
                                    <span>Voltar para a lista de grupos musculares</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </main>

                <!-- FIXED BOTTOM BAR -->
                <footer
                    class="sticky bottom-0 z-20 border-t border-gray-200 bg-white/95 px-4 py-3.5 backdrop-blur-md dark:border-gray-800 dark:bg-gray-900/95 sm:px-6"
                >
                    <div class="mx-auto flex max-w-5xl items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span
                                v-if="selectedIds.length > 0"
                                class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-violet-600 text-xs font-bold text-white shadow-sm"
                            >
                                {{ selectedIds.length }}
                            </span>
                            <span class="text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{
                                    selectedIds.length === 0
                                        ? 'Nenhum exercício selecionado'
                                        : `${selectedIds.length} exercício(s) adicionado(s) ao treino`
                                }}
                            </span>
                        </div>

                        <div class="flex items-center gap-2.5">
                            <button
                                v-if="selectedMuscleKey"
                                type="button"
                                @click="backToGroups"
                                class="rounded-xl border border-gray-300 px-3.5 py-2 text-xs sm:text-sm font-semibold text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                            >
                                Outro Grupo
                            </button>

                            <button
                                type="button"
                                @click="close"
                                class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-violet-600 px-5 py-2 text-xs sm:text-sm font-bold text-white shadow-md shadow-violet-500/25 transition hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-violet-500"
                            >
                                Concluir Seleção
                            </button>
                        </div>
                    </div>
                </footer>
            </div>
        </Transition>
    </Teleport>
</template>
