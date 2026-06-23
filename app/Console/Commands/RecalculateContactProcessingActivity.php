<?php

namespace App\Console\Commands;

use App\Models\Contact;
use Illuminate\Console\Command;

class RecalculateContactProcessingActivity extends Command
{
    protected $signature = 'contacts:recalculate-processing-activity';

    protected $description = 'Recalculate processing_activity_at and overdue_at for all contacts';

    public function handle(): int
    {
        $updated = 0;
        $cleared = 0;

        Contact::query()->orderBy('id')->chunkById(100, function ($contacts) use (&$updated, &$cleared): void {
            foreach ($contacts as $contact) {
                if ($contact->recalculateProcessingActivityFromHistory()) {
                    $updated++;
                } else {
                    $cleared++;
                }
            }
        });

        $this->info("Recalculated {$updated} contact(s), cleared {$cleared} without activity history.");

        return self::SUCCESS;
    }
}
