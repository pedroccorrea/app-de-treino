<script setup>
const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:modelValue']);

// Values match the backend App\Enums\DayOfWeek (ISO-8601: 1 = Segunda, 7 = Domingo).
const DAYS = [
    { value: 1, short: 'Seg', label: 'Segunda-feira' },
    { value: 2, short: 'Ter', label: 'Terça-feira' },
    { value: 3, short: 'Qua', label: 'Quarta-feira' },
    { value: 4, short: 'Qui', label: 'Quinta-feira' },
    { value: 5, short: 'Sex', label: 'Sexta-feira' },
    { value: 6, short: 'Sáb', label: 'Sábado' },
    { value: 7, short: 'Dom', label: 'Domingo' },
];

const isSelected = (day) => props.modelValue.includes(day);

const toggleDay = (day) => {
    const next = isSelected(day)
        ? props.modelValue.filter((d) => d !== day)
        : [...props.modelValue, day].sort((a, b) => a - b);

    emit('update:modelValue', next);
};
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <button
            v-for="day in DAYS"
            :key="day.value"
            type="button"
            :title="day.label"
            :aria-pressed="isSelected(day.value)"
            @click="toggleDay(day.value)"
            :class="[
                'flex h-10 min-w-[3rem] items-center justify-center rounded-xl px-3 text-sm font-bold transition active:scale-95',
                isSelected(day.value)
                    ? 'bg-violet-500 text-white shadow-md shadow-violet-500/30'
                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600',
            ]"
        >
            {{ day.short }}
        </button>
    </div>
</template>
