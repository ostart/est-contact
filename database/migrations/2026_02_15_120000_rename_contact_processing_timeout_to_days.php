<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('system_settings')
            ->where('key', 'contact_processing_timeout')
            ->update(['key' => 'contact_processing_timeout_days']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('system_settings')
            ->where('key', 'contact_processing_timeout_days')
            ->update(['key' => 'contact_processing_timeout']);
    }
};
