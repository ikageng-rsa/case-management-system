<?php

declare(strict_types=1);

namespace App\Services\Roles;

use RuntimeException;

class CannotRemoveLastAdministrator extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('That would leave nobody able to manage users and assign roles.');
    }
}
