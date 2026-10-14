<?php

declare(strict_types=1);

namespace App\Services\Reference;

use RuntimeException;

class CannotRemoveSystemRecord extends RuntimeException
{
    public function __construct(string $what)
    {
        parent::__construct("{$what} is used by the system itself, so it cannot be removed or retired.");
    }
}
