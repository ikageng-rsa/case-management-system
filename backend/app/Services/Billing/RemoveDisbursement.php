<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Billing\Disbursement;
use Illuminate\Support\Facades\DB;

class RemoveDisbursement
{
    /** Take back a disbursement recorded in error, with its receipt. */
    public function remove(Disbursement $disbursement): void
    {
        DB::transaction(function () use ($disbursement): void {
            $disbursement->clearMediaCollection(Disbursement::RECEIPT);
            $disbursement->delete();
        });
    }
}
