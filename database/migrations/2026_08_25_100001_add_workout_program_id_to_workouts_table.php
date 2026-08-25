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
        Schema::table('workouts', function (Blueprint $table) {
            $table->foreignId('workout_program_id')
                ->nullable()
                ->after('user_id')
                ->constrained('workout_programs')
                ->cascadeOnDelete();
        });

        $this->backfillOrphanWorkoutsIntoDefaultPrograms();
    }

    /**
     * Every workout that existed before programs did belongs to no program.
     * Group those orphans by owner and fold each user's workouts into a
     * single default, active program so nothing is left dangling.
     */
    private function backfillOrphanWorkoutsIntoDefaultPrograms(): void
    {
        $orphanUserIds = DB::table('workouts')
            ->whereNull('workout_program_id')
            ->distinct()
            ->pluck('user_id');

        foreach ($orphanUserIds as $userId) {
            $programId = DB::table('workout_programs')->insertGetId([
                'user_id' => $userId,
                'name' => 'Meu Programa Atual',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('workouts')
                ->where('user_id', $userId)
                ->whereNull('workout_program_id')
                ->update(['workout_program_id' => $programId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workout_program_id');
        });
    }
};
