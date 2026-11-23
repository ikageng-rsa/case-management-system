<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/* What a line on a bill charges for. Disbursements are shown apart from fees. */
enum BillLineKind: string
{
    case Work = 'work';
    case TimeOnTop = 'time_on_top';
    case Disbursement = 'disbursement';
    case AgreementFee = 'agreement_fee';

    public function isFee(): bool
    {
        return $this !== self::Disbursement;
    }
}
