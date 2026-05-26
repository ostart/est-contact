<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE contacts MODIFY COLUMN status ENUM(
            'not_processed',
            'assigned',
            'in_progress',
            'overdue',
            'frozen',
            'success',
            'failed'
        ) NOT NULL DEFAULT 'not_processed'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('contacts')->where('status', 'in_progress')->update(['status' => 'assigned']);

        DB::statement("ALTER TABLE contacts MODIFY COLUMN status ENUM(
            'not_processed',
            'assigned',
            'overdue',
            'frozen',
            'success',
            'failed'
        ) NOT NULL DEFAULT 'not_processed'");
    }
};
