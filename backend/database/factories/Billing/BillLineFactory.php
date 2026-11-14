<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Enums\Billing\BillLineKind;
use App\Models\Billing\Bill;
use App\Models\Billing\BillLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillLine>
 */
class BillLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bill_id' => Bill::factory(),
            'kind' => BillLineKind::Work,
            'occurred_on' => now()->toDateString(),
            'item' => 'DRAFT',
            'description' => 'Drafted the summons',
            'units' => 4,
            'rate_cents' => 5500,
            'fee_cents' => 22000,
            'disbursement_cents' => 0,
        ];
    }
}
