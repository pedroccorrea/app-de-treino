<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['workout_session_id', 'client_uuid', 'exercise_id', 'set_number', 'weight', 'reps', 'rpe', 'is_warmup', 'notes'])]
class SetLog extends Model
{
    // Phase 45: excluir um exercício/treino do histórico é recuperável. A
    // linha excluída some de toda consulta Eloquent (histórico, "Última",
    // recordes, sugestões), mas continua no banco para que uma ação antiga
    // da fila offline não a recrie (ver `SyncService::logSet`).
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'rpe' => 'decimal:1',
            'is_warmup' => 'boolean',
            // Quando a série foi corrigida no histórico (null = nunca).
            'edited_at' => 'datetime',
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
