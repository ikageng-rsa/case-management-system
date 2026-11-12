<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Billing;

use App\Support\Billing\TariffCsv;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TariffCsvTest extends TestCase
{
    protected function file(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tariff');
        file_put_contents($path, $contents);

        return $path;
    }

    public function test_it_reads_each_row_by_column_with_its_line_number(): void
    {
        $rows = TariffCsv::read($this->file("activity_code,scale,role,unit_rate,time_rate,time_block_minutes\nDRAFT,A,,49.00,,\n\nTRAVEL,,,10.50,223,15\n"));

        $this->assertCount(2, $rows);
        $this->assertSame(['line' => 2, 'activity_code' => 'DRAFT', 'scale' => 'A', 'role' => '', 'unit_rate' => '49.00', 'time_rate' => '', 'time_block_minutes' => ''], $rows[0]);
        $this->assertSame(4, $rows[1]['line']);
        $this->assertSame('15', $rows[1]['time_block_minutes']);
    }

    public function test_it_accepts_headings_saved_by_a_spreadsheet_with_a_byte_order_mark_and_capitals(): void
    {
        $rows = TariffCsv::read($this->file("\xEF\xBB\xBFActivity_Code,Scale,Role,Unit_Rate,Time_Rate,Time_Block_Minutes\nCALL,,,100,,\n"));

        $this->assertSame('CALL', $rows[0]['activity_code']);
    }

    public function test_it_refuses_a_file_without_the_templates_headings(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TariffCsv::read($this->file("code,rate\nDRAFT,49\n"));
    }

    public function test_it_reads_rand_amounts_as_cents(): void
    {
        $this->assertSame(4900, TariffCsv::cents('49'));
        $this->assertSame(4950, TariffCsv::cents('49.5'));
        $this->assertSame(1050, TariffCsv::cents('R 10,50'));
        $this->assertSame(0, TariffCsv::cents('0'));
    }

    public function test_it_does_not_guess_at_amounts_it_cannot_read(): void
    {
        $this->assertNull(TariffCsv::cents(''));
        $this->assertNull(TariffCsv::cents('1,000.00'));
        $this->assertNull(TariffCsv::cents('-5'));
        $this->assertNull(TariffCsv::cents('49.505'));
    }

    public function test_it_writes_the_headings_above_the_rows(): void
    {
        $this->assertSame(
            "activity_code,scale,role,unit_rate,time_rate,time_block_minutes\nDRAFT,,,,,\n",
            TariffCsv::write([['DRAFT', '', '', '', '', '']]),
        );
    }
}
