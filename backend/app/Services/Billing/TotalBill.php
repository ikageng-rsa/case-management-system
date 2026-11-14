<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\DrawingFeeBase;
use App\Models\Billing\Bill;
use App\Settings\BillingSettings;

class TotalBill
{
    public function __construct(
        protected BillingSettings $settings,
    ) {}

    /**
     * Work out a draft's totals from its lines: the fees, the drawing fee on the
     * fees (and the disbursements, if the firm charges it on those too), shown
     * under fees, the disbursements at cost, and what it all comes to. The
     * drawing fee's percentage is kept on the bill as it was drawn.
     */
    public function total(Bill $bill): Bill
    {
        $lines = $bill->lines()->get();

        $fees = (int) $lines->filter(fn ($line) => $line->kind->isFee())->sum('fee_cents');
        $disbursements = (int) $lines->sum('disbursement_cents');
        $percent = (int) round($this->settings->drawingFeePercent * 100);
        $base = $this->settings->drawingFeeBase === DrawingFeeBase::FeesAndDisbursements ? $fees + $disbursements : $fees;
        $drawingFee = (int) round($base * $percent / 10000);

        $bill->update([
            'fees_cents' => $fees,
            'drawing_fee_percent_hundredths' => $percent,
            'drawing_fee_cents' => $drawingFee,
            'disbursements_cents' => $disbursements,
            'total_cents' => $fees + $drawingFee + $disbursements,
        ]);

        return $bill->load('lines');
    }
}
