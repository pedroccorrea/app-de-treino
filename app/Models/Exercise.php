<?php

namespace App\Models;

use App\Enums\MuscleGroup;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'primary_muscle_group', 'secondary_muscle_groups'])]
class Exercise extends Model
{
    protected function casts(): array
    {
        return [
            'primary_muscle_group' => MuscleGroup::class,
            'secondary_muscle_groups' => AsEnumCollection::of(MuscleGroup::class),
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workouts(): BelongsToMany
    {
        return $this->belongsToMany(Workout::class, 'workout_exercises')
            ->withPivot(['order', 'target_sets', 'target_reps', 'rest_seconds', 'notes'])
            ->withTimestamps()
            ->orderByPivot('order');
    }

    public function workoutExercises(): HasMany
    {
        return $this->hasMany(WorkoutExercise::class);
    }

    public function setLogs(): HasMany
    {
        return $this->hasMany(SetLog::class);
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }

    public function scopeForUser(Builder $query, User|int $user): Builder
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $query->where(function (Builder $query) use ($userId) {
            $query->whereNull('user_id')
                ->orWhere('user_id', $userId);
        });
    }
}
