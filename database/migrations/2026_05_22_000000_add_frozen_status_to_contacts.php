<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->timestamp('frozen_until')->nullable()->after('status');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE contacts MODIFY COLUMN status ENUM(
                'not_processed',
                'assigned',
                'overdue',
                'frozen',
                'success',
                'failed'
            ) NOT NULL DEFAULT 'not_processed'");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::table('contacts')->where('status', 'frozen')->update(['status' => 'assigned']);

            DB::statement("ALTER TABLE contacts MODIFY COLUMN status ENUM(
                'not_processed',
                'assigned',
                'overdue',
                'success',
                'failed'
            ) NOT NULL DEFAULT 'not_processed'");
        }

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('frozen_until');
        });
    }
};
