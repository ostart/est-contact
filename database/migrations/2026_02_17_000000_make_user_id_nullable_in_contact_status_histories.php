<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * user_id nullable: смена статуса может выполняться системой (например, команда просрочки без авторизованного пользователя).
     */
    public function up(): void
    {
        Schema::table('contact_status_histories', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE contact_status_histories MODIFY user_id BIGINT UNSIGNED NULL');
        } else {
            Schema::table('contact_status_histories', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->change();
            });
        }

        Schema::table('contact_status_histories', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contact_status_histories', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE contact_status_histories MODIFY user_id BIGINT UNSIGNED NOT NULL');
        } else {
            Schema::table('contact_status_histories', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable(false)->change();
            });
        }

        Schema::table('contact_status_histories', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
