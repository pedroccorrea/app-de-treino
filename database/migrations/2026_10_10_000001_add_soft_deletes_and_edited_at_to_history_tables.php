<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 45 — Editar e excluir no histórico de treinos.
 *
 * Somente aditiva: três colunas novas que aceitam nulo. Nenhum registro
 * existente é alterado (todos ficam com `deleted_at`/`edited_at` nulos, ou
 * seja, exatamente como estavam: visíveis e nunca editados).
 *
 * - `deleted_at` (sessões e séries): exclusão recuperável (SoftDeletes).
 *   Treinos de teste somem do histórico e de todos os cálculos, mas a linha
 *   continua no banco — e é ela que impede a fila offline de recriar o que
 *   foi excluído (ver `SyncService::logSet`).
 * - `edited_at` (séries): quando a série foi corrigida no histórico. Um
 *   `log_set` antigo da fila offline, ocorrido ANTES da correção, não pode
 *   sobrescrevê-la.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_sessions', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('set_logs', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('set_logs', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('edited_at');
        });

        Schema::table('workout_sessions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
