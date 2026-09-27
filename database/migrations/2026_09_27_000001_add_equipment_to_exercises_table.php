<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Coluna aditiva e anulável: exercícios existentes continuam intactos
     * (equipment = null, tratado como "outro") até serem classificados sob
     * demanda pelo ExerciseEquipmentService.
     */
    public function up(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->string('equipment')->nullable()->after('secondary_muscle_groups');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropColumn('equipment');
        });
    }
};
