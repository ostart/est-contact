<?php

use App\Support\PhoneNumberHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Приводит уже сохранённые номера к E.164 для корректного unique и поиска.
     */
    public function up(): void
    {
        DB::table('contacts')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                if (! is_string($row->phone) || $row->phone === '') {
                    continue;
                }
                $normalized = PhoneNumberHelper::normalize($row->phone, PhoneNumberHelper::CONTACT_REGIONS);
                if ($normalized !== null && $normalized !== $row->phone) {
                    DB::table('contacts')->where('id', $row->id)->update(['phone' => $normalized]);
                }
            }
        });

        DB::table('users')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                if (! is_string($row->phone) || $row->phone === '') {
                    continue;
                }
                $normalized = PhoneNumberHelper::normalize($row->phone, [PhoneNumberHelper::DEFAULT_REGION]);
                if ($normalized !== null && $normalized !== $row->phone) {
                    DB::table('users')->where('id', $row->id)->update(['phone' => $normalized]);
                }
            }
        });
    }

    public function down(): void
    {
        //
    }
};
