<?php

declare(strict_types=1);

namespace App\Services\Clients;

use RuntimeException;

class CannotRemoveLastContact extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('A client must keep at least one way of being reached. Add another contact before removing this one.');
    }
}
