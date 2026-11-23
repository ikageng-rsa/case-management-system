<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/*
 * How a role's rate differs from the gazette: by a percentage of each rate, or
 * by a fixed amount on each unit. Either can be negative, so a candidate
 * attorney at half the tariff is -50%.
 */
enum TariffAdjustmentKind: string
{
    case Percent = 'percent';
    case Amount = 'amount';
}
