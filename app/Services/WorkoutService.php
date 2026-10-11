<?php

namespace App\Services;

use App\DataTransferObjects\RepRange;
use App\Enums\DayOfWeek;
use App\Enums\MuscleGroup;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class WorkoutService
{
    public function __construct(
        private readonly WorkoutProgramService $workoutProgramService,
        private readonly ExerciseEquipmentService $exerciseEquipmentService,
    ) {}

    /**
     * Fetches the user's workouts ordered by their scheduled weekday
     * (Monday → Sunday). Workouts without a scheduled day are listed last.
     *
     * @return Collection<int, Workout>
     */
    public function getUserWorkouts(User $user, bool $onlyActive = false): Collection
    {
        $query = $user->workouts()->with(['exercises', 'programs']);

        if ($onlyActive) {
            $query->active();
        }

        return $query
            ->latest()
            ->get()
            ->sortBy(function (Workout $workout) {
                $days = $workout->days_of_week;

                if (! $days || $days->isEmpty()) {
                    return PHP_INT_MAX;
                }

                return $days->map(fn (DayOfWeek $day) => $day->value)->min();
            })
            ->values();
    }

    public function todayDayOfWeek(): DayOfWeek
    {
        return DayOfWeek::from(now()->dayOfWeekIso);
    }

    /**
     * @return array<int, array{muscle: string, muscle_key: string, exercises: array<int, array{id: int, name: string, primary_muscle: string, equipment: ?string}>}>
     */
    public function getAvailableExercises(): array
    {
        return Exercise::query()
            ->global()
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Exercise $exercise) => $exercise->primary_muscle_group->value)
            ->map(function ($exercises, string $muscleKey) {
                return [
                    'muscle' => MuscleGroup::from($muscleKey)->label(),
                    'muscle_key' => $muscleKey,
                    'exercises' => $exercises->map(fn (Exercise $exercise) => [
                        'id' => $exercise->id,
                        'name' => $exercise->name,
                        'primary_muscle' => $exercise->primary_muscle_group->label(),
                        'equipment' => $exercise->equipment?->value,
                    ])->values()->all(),
                ];
            })
            ->sortBy('muscle')
            ->values()
            ->all();
    }

    public function createWorkout(User $user, array $data): Workout
    {
        $workout = DB::transaction(function () use ($user, $data) {
            $workout = $user->workouts()->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'days_of_week' => $data['days_of_week'] ?? null,
                'workout_program_id' => $data['workout_program_id']
                    ?? $this->workoutProgramService->getActiveProgram($user)?->id,
            ]);

            if ($workout->workout_program_id) {
                $workout->programs()->syncWithoutDetaching([$workout->workout_program_id]);
            }

            foreach ($data['exercises'] ?? [] as $index => $exercise) {
                $workout->workoutExercises()->create([
                    'exercise_id' => $exercise['id'],
                    'order' => $exercise['order'] ?? $index,
                    'target_sets' => $exercise['target_sets'] ?? null,
                    'target_reps' => $exercise['target_reps'] ?? null,
                    ...RepRange::columnsFor($exercise['target_reps'] ?? null),
                    'superset_group' => $this->submittedSupersetGroup($exercise),
                ]);
            }

            $this->normalizeStoredSupersetGroups($workout);

            return $workout->load('exercises');
        });

        $this->exerciseEquipmentService->ensureClassifiedInBackground($workout->exercises);

        return $workout;
    }

    /**
     * Updates a workout's own data and syncs its exercises against the
     * `workout_exercises` pivot: exercises no longer submitted are removed,
     * the rest are upserted with their (possibly new) order/sets/reps.
     * target_reps_min/max are derived from the submitted text on every save
     * through the single interpretation rule (RepRange).
     *
     * `superset_group` só é alterado quando o exercício o traz no payload:
     * formulários e filas offline antigos (sem o campo) preservam o grupo
     * gravado. Ao final, os grupos são normalizados sobre a ordem final.
     */
    public function updateWorkout(Workout $workout, array $data): Workout
    {
        $workout = DB::transaction(function () use ($workout, $data) {
            $workout->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'days_of_week' => $data['days_of_week'] ?? null,
            ]);

            $exercises = collect($data['exercises'] ?? []);

            $workout->workoutExercises()
                ->whereNotIn('exercise_id', $exercises->pluck('id'))
                ->delete();

            foreach ($exercises as $index => $exercise) {
                $workout->workoutExercises()->updateOrCreate(
                    ['exercise_id' => $exercise['id']],
                    [
                        'order' => $exercise['order'] ?? $index,
                        'target_sets' => $exercise['target_sets'] ?? null,
                        'target_reps' => $exercise['target_reps'] ?? null,
                        ...RepRange::columnsFor($exercise['target_reps'] ?? null),
                        ...(array_key_exists('superset_group', $exercise)
                            ? ['superset_group' => $this->submittedSupersetGroup($exercise)]
                            : []),
                    ]
                );
            }

            $this->normalizeStoredSupersetGroups($workout);

            return $workout->load('exercises');
        });

        $this->exerciseEquipmentService->ensureClassifiedInBackground($workout->exercises);

        return $workout;
    }

    /**
     * Persists the new sort order of exercises in the `workout_exercises` pivot.
     * A nova ordem pode separar um grupo combinado, por isso os grupos são
     * normalizados de novo logo depois de gravar as posições.
     *
     * @param  array<int>  $exerciseIds  Ordered array of exercise IDs.
     */
    public function reorderExercises(Workout $workout, array $exerciseIds): void
    {
        DB::transaction(function () use ($workout, $exerciseIds) {
            foreach ($exerciseIds as $position => $exerciseId) {
                $workout->workoutExercises()
                    ->where('exercise_id', $exerciseId)
                    ->update(['order' => $position]);
            }

            $this->normalizeStoredSupersetGroups($workout);
        });
    }

    /**
     * Regra única dos exercícios combinados (bi-set/tri-set). Recebe o
     * `superset_group` de cada exercício JÁ na ordem final da ficha e devolve,
     * na mesma posição, o grupo limpo:
     *
     * - Um grupo é um trecho contínuo de vizinhos com o mesmo número; um
     *   trecho com só 1 exercício volta a ser exercício sozinho (null).
     * - Mesmo número em posições não vizinhas: cada trecho contínuo é
     *   avaliado separadamente e, se válido, vira um grupo DISTINTO (ex.:
     *   [1, 1, null, 1, 1] → [1, 1, null, 2, 2]). Ninguém se agrupa com quem
     *   não é vizinho.
     * - Os grupos válidos são renumerados 1, 2, 3... na ordem em que
     *   aparecem: o número só significa "quem está junto".
     *
     * @param  array<int, ?int>  $groups
     * @return array<int, ?int>
     */
    public function normalizeSupersetGroups(array $groups): array
    {
        $groups = array_values($groups);
        $normalized = array_fill(0, count($groups), null);
        $nextGroup = 1;
        $runStart = 0;

        for ($i = 1; $i <= count($groups); $i++) {
            $runContinues = $i < count($groups)
                && $groups[$i] !== null
                && $groups[$i] === $groups[$runStart];

            if ($runContinues) {
                continue;
            }

            if ($groups[$runStart] !== null && $i - $runStart >= 2) {
                for ($j = $runStart; $j < $i; $j++) {
                    $normalized[$j] = $nextGroup;
                }

                $nextGroup++;
            }

            $runStart = $i;
        }

        return $normalized;
    }

    /**
     * Aplica `normalizeSupersetGroups` aos exercícios gravados da ficha (na
     * ordem do pivot) e grava só as linhas cujo grupo mudou. Deve rodar
     * dentro da transação de quem alterou a ficha.
     */
    private function normalizeStoredSupersetGroups(Workout $workout): void
    {
        $rows = $workout->workoutExercises()->orderBy('order')->orderBy('id')->get();

        $current = $rows
            ->map(fn (WorkoutExercise $row) => $row->superset_group !== null ? (int) $row->superset_group : null)
            ->values()
            ->all();
        $normalized = $this->normalizeSupersetGroups($current);

        foreach ($rows->values() as $position => $row) {
            if ($current[$position] !== $normalized[$position]) {
                $row->update(['superset_group' => $normalized[$position]]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $exercise
     */
    private function submittedSupersetGroup(array $exercise): ?int
    {
        $group = $exercise['superset_group'] ?? null;

        return $group === null || $group === '' ? null : (int) $group;
    }

    /**
     * Flips a workout between active and archived.
     */
    public function toggleArchive(Workout $workout): Workout
    {
        $workout->update(['is_active' => ! $workout->is_active]);

        return $workout;
    }

    /**
     * Deletes a workout and everything that only makes sense in its context
     * (the `workout_exercises` pivot rows cascade at the DB level; workout
     * sessions are preserved but orphaned via `nullOnDelete`).
     */
    public function destroy(Workout $workout): void
    {
        DB::transaction(function () use ($workout) {
            $workout->delete();
        });
    }
}
