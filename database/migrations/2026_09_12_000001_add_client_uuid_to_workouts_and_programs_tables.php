<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workout_programs', function (Blueprint $table) {
            $table->string('client_uuid')->nullable()->after('user_id');
            $table->unique(['user_id', 'client_uuid']);
        });

        Schema::table('workouts', function (Blueprint $table) {
            $table->string('client_uuid')->nullable()->after('user_id');
            $table->unique(['user_id', 'client_uuid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workout_programs', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'client_uuid']);
            $table->dropColumn('client_uuid');
        });

        Schema::table('workouts', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'client_uuid']);
            $table->dropColumn('client_uuid');
        });
    }
};
