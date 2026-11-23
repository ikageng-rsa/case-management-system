<?php

declare(strict_types=1);

use App\Enums\Billing\BillingTiming;
use App\Enums\Billing\DrawingFeeBase;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /*
     * The firm's terms as they were described: a quarter of what a state claim
     * pays out, 3.5% of an administered estate, and a 5% drawing fee on fees.
     */
    public function up(): void
    {
        $this->migrator->add('billing.contingencyPercent', 25.0);
        $this->migrator->add('billing.estatePercent', 3.5);
        $this->migrator->add('billing.drawingFeePercent', 5.0);
        $this->migrator->add('billing.drawingFeeBase', DrawingFeeBase::Fees->value);
        $this->migrator->add('billing.billingTiming', BillingTiming::Manual->value);
    }
};
