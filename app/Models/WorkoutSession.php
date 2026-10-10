<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'workout_id', 'client_uuid', 'started_at', 'completed_at', 'notes'])]
class WorkoutSession extends Model
{
    use HasFactory;

    // Phase 45: treino excluído do histórico (mesmo motivo de `SetLog`). As
    // séries são excluídas junto (`WorkoutHistoryService::deleteSession`),
    // para que nenhuma consulta direta em `set_logs` as conte.
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }

    public function setLogs(): HasMany
    {
        return $this->hasMany(SetLog::class);
    }
}
