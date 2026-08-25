<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkoutProgram;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class WorkoutProgramService
{
    /**
     * Returns the user's programs (active first, then most recently
     * archived), each carrying its workout count and workouts for display.
     *
     * @return Collection<int, WorkoutProgram>
     */
    public function getUserPrograms(User $user): Collection
    {
        return $user->workoutPrograms()
            ->withCount('workouts')
            ->with('workouts')
            ->orderByDesc('is_active')
            ->orderByDesc('archived_at')
            ->orderByDesc('created_at')
            ->get();
    }

    public function getActiveProgram(User $user): ?WorkoutProgram
    {
        return $user->workoutPrograms()->where('is_active', true)->first();
    }

    /**
     * The very first program a user creates becomes active automatically;
     * subsequent ones must be activated explicitly so exclusivity holds.
     */
    public function createProgram(User $user, array $data): WorkoutProgram
    {
        $isFirstProgram = ! $user->workoutPrograms()->exists();

        return $user->workoutPrograms()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $isFirstProgram,
        ]);
    }

    /**
     * Activates the given program and archives whichever program was
     * previously active for the same user, so exactly one stays active.
     */
    public function activateProgram(WorkoutProgram $program): WorkoutProgram
    {
        return DB::transaction(function () use ($program) {
            WorkoutProgram::query()
                ->where('user_id', $program->user_id)
                ->where('id', '!=', $program->id)
                ->where('is_active', true)
                ->update(['is_active' => false, 'archived_at' => now()]);

            $program->update(['is_active' => true, 'archived_at' => null]);

            return $program;
        });
    }

    public function archiveProgram(WorkoutProgram $program): WorkoutProgram
    {
        $program->update(['is_active' => false, 'archived_at' => now()]);

        return $program;
    }

    /**
     * Deletes the program; its workouts cascade at the database level
     * (`workout_program_id` is `cascadeOnDelete`), which in turn cascades
     * their `workout_exercises` pivot rows and orphans past sessions.
     */
    public function deleteProgram(WorkoutProgram $program): void
    {
        DB::transaction(function () use ($program) {
            $program->delete();
        });
    }
}
