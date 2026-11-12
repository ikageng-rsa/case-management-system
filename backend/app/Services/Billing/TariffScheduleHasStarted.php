<?php

declare(strict_types=1);

namespace App\Services\Billing;

use RuntimeException;

class TariffScheduleHasStarted extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This tariff is already in force, so it stays on record. Upload a newer one to replace it.');
    }
}
