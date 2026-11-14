<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/*
 * How a client is charged on a matter. Under a mandate the work is billed by
 * statement at the firm's rates. Under a contingency agreement the firm takes a
 * share of what a state claim pays out, and under an estate agreement a share
 * of the estate funds, with no other fees charged to the client.
 */
enum FeeAgreementKind: string
{
    case Mandate = 'mandate';
    case Contingency = 'contingency';
    case Estate = 'estate';

    public function label(): string
    {
        return match ($this) {
            self::Mandate => 'Mandate',
            self::Contingency => 'Contingency',
            self::Estate => 'Estate',
        };
    }

    /** Whether the fee is a share of an amount rather than the work billed. */
    public function isPercentage(): bool
    {
        return $this !== self::Mandate;
    }
}
