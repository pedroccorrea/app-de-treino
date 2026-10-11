<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Somente aditiva: grupo de exercício combinado (bi-set/tri-set) na ficha.
 * Exercícios vizinhos com o mesmo número formam um grupo; nulo = exercício
 * sozinho. Registros existentes ficam todos com nulo (nenhum grupo), e a
 * trava unique(workout_id, exercise_id) não é tocada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->unsignedSmallInteger('superset_group')->nullable()->after('order');
        });
    }

    public function down(): void
    {
        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->dropColumn('superset_group');
        });
    }
};
