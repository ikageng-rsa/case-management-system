<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/*
 * Who a bill goes to and what it charges. A statement of account bills the
 * client for the work and costs on a mandate. A bill of cost claims the costs
 * from the other side, such as a state department, on the court's tariff. A fee
 * bill charges the client the agreed share once a contingency or estate matter
 * is finalised.
 */
enum BillKind: string
{
    case Statement = 'statement';
    case BillOfCost = 'bill_of_cost';
    case AgreementFee = 'agreement_fee';

    public function label(): string
    {
        return match ($this) {
            self::Statement => 'Statement of account',
            self::BillOfCost => 'Bill of cost',
            self::AgreementFee => 'Fee bill',
        };
    }
}
