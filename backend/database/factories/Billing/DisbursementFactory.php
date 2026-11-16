<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Enums\Billing\FirmAccount;
use App\Models\Billing\Disbursement;
use App\Models\Matter\Matter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Disbursement>
 */
class DisbursementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'matter_id' => Matter::factory(),
            'incurred_on' => now()->toDateString(),
            'description' => 'Sheriff\'s fee for service of summons',
            'amount_cents' => 45000,
            'paid_from' => FirmAccount::Business,
        ];
    }
}
