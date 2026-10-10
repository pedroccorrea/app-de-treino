<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Somente aditiva: mínimo e máximo da meta de repetições, preenchidos apenas
 * nas gravações novas. Registros antigos ficam com as colunas nulas e são
 * interpretados na leitura a partir de `target_reps` (ver RepRange).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->unsignedSmallInteger('target_reps_min')->nullable()->after('target_reps');
            $table->unsignedSmallInteger('target_reps_max')->nullable()->after('target_reps_min');
        });
    }

    public function down(): void
    {
        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->dropColumn(['target_reps_min', 'target_reps_max']);
        });
    }
};
