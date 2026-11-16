<?php

declare(strict_types=1);

namespace App\Services\Billing;

use RuntimeException;

/*
 * A bill that cannot be drawn as asked, with every reason, so the work can be
 * put right in one pass rather than left off the bill without anyone noticing.
 */
class BillRefused extends RuntimeException
{
    /** @param  list<string>  $problems */
    public function __construct(public readonly array $problems)
    {
        parent::__construct(implode(' ', $problems));
    }
}
