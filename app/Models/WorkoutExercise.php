<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workout_id', 'exercise_id', 'order', 'target_sets', 'target_reps', 'target_reps_min', 'target_reps_max', 'rest_seconds', 'notes', 'superset_group'])]
class WorkoutExercise extends Model
{
    /**
     * Maior número aceito na entrada para `superset_group` (grupo de exercício
     * combinado). Depois de normalizado, o número nunca passa da quantidade de
     * grupos da ficha; o limite só barra lixo na validação.
     */
    public const MAX_SUPERSET_GROUP = 100;

    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
