<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Spatie config uses model_morph_key => 'user_id', but model_has_permissions
     * may have been created with default column name 'model_id'. Align column name.
     */
    public function up(): void
    {
        $tableName = 'model_has_permissions';

        if (!Schema::hasTable($tableName)) {
            return;
        }

        if (!Schema::hasColumn($tableName, 'model_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropForeign(['permission_id']);
        });

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropPrimary('model_has_permissions_permission_model_type_primary');
        });

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropIndex('model_has_permissions_model_id_model_type_index');
        });

        Schema::table($tableName, function (Blueprint $table) {
            $table->renameColumn('model_id', 'user_id');
        });

        Schema::table($tableName, function (Blueprint $table) {
            $table->index(['user_id', 'model_type'], 'model_has_permissions_user_id_model_type_index');
            $table->primary(['permission_id', 'user_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = 'model_has_permissions';

        if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'user_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropForeign(['permission_id']);
        });

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropPrimary('model_has_permissions_permission_model_type_primary');
        });

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropIndex('model_has_permissions_user_id_model_type_index');
        });

        Schema::table($tableName, function (Blueprint $table) {
            $table->renameColumn('user_id', 'model_id');
        });

        Schema::table($tableName, function (Blueprint $table) {
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
        });
    }
};
