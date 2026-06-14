<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date');
            $table->string('metric_key', 64);
            $table->unsignedInteger('value')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['snapshot_date', 'metric_key']);
            $table->index('snapshot_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_metric_snapshots');
    }
};
