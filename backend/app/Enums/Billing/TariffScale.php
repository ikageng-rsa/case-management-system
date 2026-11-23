<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/*
 * The scale of a court tariff a rate is quoted under. Which scale a bill of
 * cost falls on is chosen when it is drawn, so nothing here maps a court or a
 * claim amount to a scale.
 */
enum TariffScale: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';
}
