<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Coluna aditiva e anulável: exercícios existentes continuam intactos
     * (movement_pattern = null, tratado como "outro") até serem classificados
     * sob demanda pelo ExerciseMovementPatternService. O grupo muscular
     * principal já existe (primary_muscle_group) e é reaproveitado.
     */
    public function up(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->string('movement_pattern')->nullable()->after('equipment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropColumn('movement_pattern');
        });
    }
};
