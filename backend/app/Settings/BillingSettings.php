<?php

declare(strict_types=1);

namespace App\Settings;

use App\Enums\Billing\BillingTiming;
use App\Enums\Billing\DrawingFeeBase;
use Spatie\LaravelSettings\Settings;

/*
 * How the firm bills, set by the firm rather than written into the code. The
 * percentages are the defaults a new fee agreement starts from; an agreement
 * keeps the percentage it was signed at.
 */
class BillingSettings extends Settings
{
    public float $contingencyPercent;

    public float $estatePercent;

    public float $drawingFeePercent;

    public DrawingFeeBase $drawingFeeBase;

    public BillingTiming $billingTiming;

    public static function group(): string
    {
        return 'billing';
    }
}
