<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Billing;

use App\Enums\Billing\TariffAdjustmentKind;
use App\Models\Billing\TariffAdjustment;
use PHPUnit\Framework\TestCase;

class TariffAdjustmentTest extends TestCase
{
    protected function adjustment(TariffAdjustmentKind $kind, int $value): TariffAdjustment
    {
        return (new TariffAdjustment)->forceFill(['kind' => $kind, 'value' => $value]);
    }

    public function test_a_percentage_scales_the_rate_to_the_cent(): void
    {
        // A director at 45.45% above the associate rate of R 1 440.00.
        $this->assertSame(209448, $this->adjustment(TariffAdjustmentKind::Percent, 4545)->apply(144000));
        $this->assertSame(72000, $this->adjustment(TariffAdjustmentKind::Percent, -5000)->apply(144000));
    }

    public function test_an_amount_is_added_to_each_unit(): void
    {
        // R 500 for drafting a summons becomes R 515.
        $this->assertSame(51500, $this->adjustment(TariffAdjustmentKind::Amount, 1500)->apply(50000));
    }

    public function test_a_rate_never_goes_below_nothing(): void
    {
        $this->assertSame(0, $this->adjustment(TariffAdjustmentKind::Amount, -10000)->apply(4900));
        $this->assertSame(0, $this->adjustment(TariffAdjustmentKind::Percent, -15000)->apply(4900));
    }

    public function test_it_reads_as_a_signed_percentage_or_amount(): void
    {
        $this->assertSame('+45.45%', $this->adjustment(TariffAdjustmentKind::Percent, 4545)->label());
        $this->assertSame('−50%', $this->adjustment(TariffAdjustmentKind::Percent, -5000)->label());
        $this->assertSame('+R 15.00', $this->adjustment(TariffAdjustmentKind::Amount, 1500)->label());
    }
}
