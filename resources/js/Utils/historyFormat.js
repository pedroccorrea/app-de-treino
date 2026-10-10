// Formatadores compartilhados pelas telas de histórico de treinos
// (`Pages/WorkoutHistory/*` e `Components/WorkoutHistory/*`).

// Texto neutro para sessões cuja ficha foi excluída (`workout_id` nulo).
export const DELETED_WORKOUT_LABEL = 'Ficha excluída';

const numberFormatter = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 });

export const formatNumber = (value) => numberFormatter.format(Number(value));

export const formatSessionDate = (iso) =>
    new Date(iso).toLocaleDateString('pt-BR', {
        weekday: 'short',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });

export const formatSessionTime = (iso) =>
    new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

export const formatDuration = (minutes) => {
    if (minutes === null || minutes === undefined) return null;
    if (minutes < 60) return `${minutes} min`;

    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    return rest === 0 ? `${hours}h` : `${hours}h ${String(rest).padStart(2, '0')}min`;
};

export const formatVolume = (volume) => `${formatNumber(volume)} kg`;

// Mesma convenção da tela de treino (`ExerciseActiveCard.vue`): série sem
// carga aparece como "—kg".
export const formatSet = (set) => `${set.weight !== null ? formatNumber(set.weight) : '—'}kg × ${set.reps}`;
