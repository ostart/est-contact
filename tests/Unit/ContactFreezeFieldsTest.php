<?php

namespace Tests\Unit;

use App\Filament\Support\ContactFreezeFields;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ContactFreezeFieldsTest extends TestCase
{
    public function test_split_frozen_until_for_form_accepts_carbon_instance(): void
    {
        $frozenUntil = Carbon::parse('2026-07-31 00:00:00', 'UTC');

        $data = ContactFreezeFields::splitFrozenUntilForForm([
            'frozen_until' => $frozenUntil,
        ]);

        $this->assertSame('2026-07-31', $data['freeze_date']);
    }

    public function test_split_frozen_until_for_form_accepts_iso_string(): void
    {
        $data = ContactFreezeFields::splitFrozenUntilForForm([
            'frozen_until' => '2026-07-30T21:00:00.000000Z',
        ]);

        $this->assertSame('2026-07-31', $data['freeze_date']);
    }

    public function test_split_frozen_until_for_form_accepts_legacy_datetime_utc_serialization(): void
    {
        $data = ContactFreezeFields::splitFrozenUntilForForm([
            'frozen_until' => '1785445200UTCC',
        ]);

        $this->assertSame('2026-07-31', $data['freeze_date']);
    }

    public function test_format_frozen_until_display_uses_moscow_timezone(): void
    {
        $display = ContactFreezeFields::formatFrozenUntilDisplay(
            Carbon::parse('2026-07-30 21:00:00', 'UTC'),
        );

        $this->assertSame('31.07.2026', $display);
    }
}
