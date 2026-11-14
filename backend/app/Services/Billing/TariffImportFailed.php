<?php

declare(strict_types=1);

namespace App\Services\Billing;

use RuntimeException;

/*
 * A tariff file is taken whole or not at all, so a mistake on one line stops
 * the upload. Every problem found is listed, each with the line it is on, so
 * the file can be fixed in one pass.
 */
class TariffImportFailed extends RuntimeException
{
    /** @param  list<string>  $problems */
    public function __construct(public readonly array $problems)
    {
        parent::__construct('The tariff file has '.count($problems).' problem(s): '.implode(' ', $problems));
    }
}
