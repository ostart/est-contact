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
    protected $description = 'Unfreeze expired contacts and mark overdue ones';

    public function handle(): int
    {
        $unfrozen = 0;
        $frozenContacts = Contact::query()
            ->where('status', ContactStatus::FROZEN->value)
            ->whereNotNull('frozen_until')
            ->where('frozen_until', '<=', now())
            ->get();

        foreach ($frozenContacts as $contact) {
            $contact->update(['status' => ContactStatus::ASSIGNED]);
            $unfrozen++;
        }

        $timeout = (int) SystemSetting::get('contact_processing_timeout_days', 30);
        $cutoffDate = now()->subDays($timeout);

        $overdueContacts = Contact::where('status', ContactStatus::ASSIGNED->value)
            ->whereHas('statusHistories', function ($query) use ($cutoffDate) {
                $query->where('new_status', ContactStatus::ASSIGNED->value)
                    ->where('created_at', '<=', $cutoffDate);
            })
            ->get();

        $overdue = 0;
        foreach ($overdueContacts as $contact) {
            $contact->update(['status' => ContactStatus::OVERDUE]);
            $overdue++;
        }

        $this->info("Unfroze {$unfrozen} contact(s), marked {$overdue} as overdue.");

        return self::SUCCESS;
    }
}

