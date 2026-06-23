<?php

use App\Models\Contact;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->timestamp('processing_activity_at')->nullable()->after('frozen_until');
            $table->timestamp('overdue_at')->nullable()->after('processing_activity_at');

            $table->index('overdue_at');
        });

        Contact::query()->orderBy('id')->chunkById(100, function ($contacts): void {
            foreach ($contacts as $contact) {
                $contact->recalculateProcessingActivityFromHistory();
            }
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['overdue_at']);
            $table->dropColumn(['processing_activity_at', 'overdue_at']);
        });
    }
};
