<?php

namespace Tests\Unit;

use App\Filament\Support\ContactInfoCopy;
use PHPUnit\Framework\TestCase;

class ContactInfoCopyTest extends TestCase
{
    public function test_format_joins_name_phone_and_district_with_newlines(): void
    {
        $this->assertSame(
            "Иванов Иван\n+79990001122\nЦентральный",
            ContactInfoCopy::format('Иванов Иван', '+79990001122', 'Центральный'),
        );
    }

    public function test_format_trims_values_and_skips_blanks(): void
    {
        $this->assertSame(
            "Иванов Иван\n+79990001122",
            ContactInfoCopy::format('  Иванов Иван  ', '+79990001122', '   '),
        );
    }
}
