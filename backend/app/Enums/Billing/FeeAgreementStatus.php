<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/*
 * Where an agreement stands. A matter has one active agreement at a time. A
 * contingency claim that fails is marked failed, and the client can then be
 * billed by statement under a new mandate. One that pays out is finalised with
 * the amount received.
 */
enum FeeAgreementStatus: string
{
    case Active = 'active';
    case Failed = 'failed';
    case Finalised = 'finalised';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Failed => 'Claim failed',
            self::Finalised => 'Finalised',
        };
    }
}
