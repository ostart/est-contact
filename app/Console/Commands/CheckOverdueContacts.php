<?php

namespace App\Console\Commands;

use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\SystemSetting;
use Illuminate\Console\Command;

class CheckOverdueContacts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contacts:check-overdue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and mark overdue contacts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timeout = (int) SystemSetting::get('contact_processing_timeout_days', 30);
        $cutoffDate = now()->subDays($timeout);

        $overdueContacts = Contact::where('status', ContactStatus::ASSIGNED->value)
            ->whereHas('statusHistories', function ($query) use ($cutoffDate) {
                $query->where('new_status', ContactStatus::ASSIGNED->value)
                    ->where('created_at', '<=', $cutoffDate);
            })
            ->get();

        $count = 0;
        foreach ($overdueContacts as $contact) {
            $contact->update(['status' => ContactStatus::OVERDUE]);
            $count++;
        }

        $this->info("Marked {$count} contact(s) as overdue.");
        return 0;
    }
}

