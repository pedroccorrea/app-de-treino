<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('program_workouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workout_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->unique(['workout_program_id', 'workout_id']);
        });

        $this->backfillExistingLinks();
    }

    /**
     * Every workout that already points at a program via its
     * `workout_program_id` column gets the equivalent row in the new pivot
     * table, so the N:N relationship starts out consistent with the old
     * 1:N one.
     */
    private function backfillExistingLinks(): void
    {
        DB::table('workouts')
            ->whereNotNull('workout_program_id')
            ->orderBy('id')
            ->get(['id', 'workout_program_id'])
            ->each(function (object $workout) {
                DB::table('program_workouts')->insert([
                    'workout_program_id' => $workout->workout_program_id,
                    'workout_id' => $workout->id,
                    'order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_workouts');
    }
};
