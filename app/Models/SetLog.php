<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workout_session_id', 'exercise_id', 'set_number', 'weight', 'reps', 'rpe', 'is_warmup', 'notes'])]
class SetLog extends Model
{
    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'rpe' => 'decimal:1',
            'is_warmup' => 'boolean',
        ];
    }

    public function workoutSession(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
